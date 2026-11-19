#!/bin/bash
# Live checks against the Craft 6 harness (~/Sites/plugin-testing-craft6).
#   Templates: tests/Harness/templates/* → resources/views/bunting/ (removed at the end)
#   Fixtures:  tests/Harness/fixtures.php (users bunting_alice/bob/carol, flags bunting-*), gql-fixtures.php
#   Routes:    the harness routes/web.php has `bunting/route-on` + `bunting/route-off` (bunting:<flag> middleware)
#   Multisite: run with MULTISITE=1 to add Bunting's own `buntingfr` site (under bin/harness-lock) and remove it
#              afterwards; the section is skipped when that site doesn't exist.
set -uo pipefail
exec < /dev/null
H=/Users/jholt/Sites/plugin-testing-craft6
SRC=$(cd "$(dirname "$0")" && pwd)
U=https://plugin-testing-craft6.ddev.site
cd "$H" || exit 2
mkdir -p resources/views/bunting
cp -R "$SRC"/templates/. resources/views/bunting/
pass=0 fail=0
ok() { if [ "$2" = "$3" ]; then echo "✓ $1"; pass=$((pass+1)); else echo "✗ $1 (got '$2', want '$3')"; fail=$((fail+1)); fi; }
tk() { ddev artisan tinker --execute "$1" 2>/dev/null | grep -v -E 'WARNING|DEPRECATED|^\s*$' | tail -1; }
F='app(JustinHoltWeb\Bunting\Flags\Flags::class)'
check() { ddev artisan craft:bunting:check "$@" 2>&1 | grep -v -E 'WARNING|DEPRECATED|Failed to run|^\s*$' | tail -1 | sed 's/^ *//; s/ *$//'; }
rollout() { tk "$F->saveRollout($F->get('$1'), $2); echo 1;" >/dev/null; }
# flags <jar|''> <page> [site prefix] [query]: "flag=value,…" from the twig/blade test page (value = feature()/bunting.active())
flags() { curl -sk ${1:+-b "$1"} "$U${3:-}/bunting/$2${4:-}" | tr -d ' ' | grep -oE 'data-flag="[a-z-]+">[a-z/]+' | sed 's/data-flag="//; s/">/=/' | tr '\n' ',' | sed 's/,$//'; }
flag() { flags "$1" "$2" "${4:-}" "${5:-}" | tr ',' '\n' | grep "^$3=" | cut -d= -f2; }
code() { curl -sk -o /dev/null -w '%{http_code}' "$@"; }
# login <username> → cookie jar (front-end session)
login() {
  local j; j=$(mktemp)
  curl -sk -c "$j" -o /dev/null "$U/admin/login"
  local x; x=$(awk '$6=="XSRF-TOKEN"{print $7}' "$j" | python3 -I -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))')
  local c; c=$(curl -sk -b "$j" -c "$j" -o /tmp/bunting-login.json -w '%{http_code}' -H 'Accept: application/json' -H "X-XSRF-TOKEN: $x" -X POST "$U/actions/users/login" \
    --data-urlencode "loginName=$1" --data-urlencode "password=${2:-bunting-pass-1}")
  [ "$c" = 200 ] || echo "login $1 failed: $c $(head -c 200 /tmp/bunting-login.json)" >&2
  rm -f /tmp/bunting-login.json
  echo "$j"
}
xsrf() { awk '$6=="XSRF-TOKEN"{print $7}' "$1" | python3 -I -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))'; }
# post <jar> <path> <curl -d args…> → status code
post() { local j=$1 p=$2; shift 2; curl -sk -b "$j" -c "$j" -X POST -H "X-XSRF-TOKEN: $(xsrf "$j")" -o /dev/null -w '%{http_code}' "$@" "$U/admin/$p"; }

# ---- Fixtures ----
cp "$SRC"/fixtures.php storage/bunting-fixtures.php
cp "$SRC"/retry.php storage/bunting-retry.php
cp "$SRC"/gql-fixtures.php storage/bunting-gql-fixtures.php
cp "$SRC"/cleanup.php storage/bunting-cleanup.php
cp "$SRC"/multisite-fixtures.php storage/bunting-multisite-fixtures.php
cp "$SRC"/multisite-remove.php storage/bunting-multisite-remove.php
PEOPLE=$(tk "require 'storage/bunting-fixtures.php';")
pick() { echo "$PEOPLE" | python3 -I -c "import json,sys; print(json.load(sys.stdin)['$1']['$2'])"; }
ALICE=$(pick alice id); BOB=$(pick bob id); CAROL=$(pick carol id); ALICE_UID=$(pick alice uid); CAROL_UID=$(pick carol uid)
ok "fixtures: three users" "$([ -n "$ALICE" ] && [ -n "$BOB" ] && [ -n "$CAROL" ] && echo yes)" "yes"
ok "fixtures: seven flags" "$(tk "echo count(array_filter(array_keys($F->all()), fn(\$h) => str_starts_with(\$h, 'bunting-')));")" "7"
ok "Pennant store is Bunting's, on its own table" "$(tk "echo config('pennant.default').'/'.config('pennant.stores.bunting.table').'/'.(Illuminate\Support\Facades\Schema::hasTable('features') ? 'features-table' : 'no-features-table');")" "bunting/bunting_features/no-features-table"

# ---- Rules (console explain) ----
ok "rules: staff member is in (group)" "$(check bunting-nav --user=bunting_alice)" "on (group)"
ok "rules: non-member is out" "$(check bunting-nav --user=bunting_bob)" "off (no-match)"
ok "rules: guests are out" "$(check bunting-nav)" "off (no-match)"
ok "on: everyone" "$(check bunting-on)/$(check bunting-on --user=bunting_bob)" "on (on)/on (on)"
ok "off: no one" "$(check bunting-off --user=bunting_alice)" "off (off)"
ok "check: unknown flag is an error" "$(check bunting-nope)" "Unknown flag."
ok "check: unknown user is an error" "$(check bunting-on --user=nobody_here)" "Unknown user."
ok "check: unknown site is an error" "$(check bunting-on --site=nope | grep -c 'No site')" "1"
# Percentage: bucket(flag uid, user uid) < pct*100 — the same users stay in as it grows.
expect_pct() { tk "\$f=$F->get('bunting-pct'); echo JustinHoltWeb\Bunting\Flags\Evaluator::bucket(\$f->uid, '$1') < $2 * 100 ? 'on (rollout)' : 'off (no-match)';"; }
for p in 50 100; do
  rollout bunting-pct "['percentage' => $p]"
  ok "rollout $p%: bob in or out as his bucket says" "$(check bunting-pct --user=bunting_bob)" "$(expect_pct "$(tk "echo CraftCms\Cms\User\Elements\User::find()->id($BOB)->one()->uid;")" $p)"
done
ok "rollout 100%: everyone signed in" "$(check bunting-pct --user=bunting_carol)" "on (rollout)"
ok "rollout 100%: still not guests (no visitor cookie)" "$(check bunting-pct)" "off (no-match)"
rollout bunting-pct "['percentage' => 0]"
ok "rollout 0%: no one" "$(check bunting-pct --user=bunting_carol)" "off (no-match)"
rollout bunting-pct "['percentage' => 50]"

# ---- Overrides ----
tk "\$u=CraftCms\Cms\User\Elements\User::find()->id($CAROL)->one(); $F->setOverride($F->get('bunting-off'), \$u->id, \$u->uid, true, 'carol'); echo 1;" >/dev/null
ok "override on beats an off flag" "$(check bunting-off --user=bunting_carol)" "on (override)"
tk "\$u=CraftCms\Cms\User\Elements\User::find()->id($ALICE)->one(); $F->setOverride($F->get('bunting-nav'), \$u->id, \$u->uid, false, 'alice'); echo 1;" >/dev/null
ok "override off beats a group rule" "$(check bunting-nav --user=bunting_alice)" "off (override)"
tk "\$u=CraftCms\Cms\User\Elements\User::find()->id($ALICE)->one(); $F->setOverride($F->get('bunting-nav'), \$u->id, \$u->uid, null, 'alice'); echo 1;" >/dev/null
ok "removing the override restores the rule" "$(check bunting-nav --user=bunting_alice)" "on (group)"

# ---- Pennant storage ----
STORE='Laravel\Pennant\Feature::store("bunting")'
SCOPE="app(JustinHoltWeb\Bunting\Bunting::class)->scope($ALICE)"
ok "Feature::store('bunting')->for(scope) reads Bunting's rules" "$(tk "echo json_encode($STORE->for($SCOPE)->active('bunting-nav'));")" "true"
ok "Feature::active() (default store) for a Bunting scope" "$(tk "echo json_encode(Laravel\Pennant\Feature::for($SCOPE)->active('bunting-nav'));")" "true"
ok "signed-in values are stored per site + user" "$(tk "echo Illuminate\Support\Facades\DB::table('bunting_features')->where('name','bunting-nav')->where('scope','like','%|user:$ALICE_UID')->count();")" "1"
rollout bunting-nav "['percentage' => 1]"
ok "a rollout change purges the flag's stored values" "$(tk "echo Illuminate\Support\Facades\DB::table('bunting_features')->where('name','bunting-nav')->count();")" "0"
rollout bunting-nav "['percentage' => 0]"
tk "echo json_encode($STORE->for($SCOPE)->active('bunting-nav'));" >/dev/null
tk "CraftCms\Cms\Support\Facades\Users::assignUserToGroups($ALICE, []); echo 1;" >/dev/null
ok "leaving a group purges the user's stored values" "$(tk "echo Illuminate\Support\Facades\DB::table('bunting_features')->where('scope','like','%|user:$ALICE_UID')->count();")" "0"
ok "… and the group rule no longer applies" "$(check bunting-nav --user=bunting_alice)" "off (no-match)"
tk "CraftCms\Cms\Support\Facades\Users::assignUserToGroups($ALICE, [CraftCms\Cms\Support\Facades\UserGroups::getGroupByHandle('staff')->id]); echo 1;" >/dev/null
ok "rejoining brings it back" "$(check bunting-nav --user=bunting_alice)" "on (group)"
ok "app-defined features still work on the Bunting store" "$(tk "Laravel\Pennant\Feature::define('bunting-appdefined', fn () => true); echo json_encode(Laravel\Pennant\Feature::for($SCOPE)->active('bunting-appdefined'));")" "true"
ok "unknown features are off" "$(tk "echo json_encode(Laravel\Pennant\Feature::active('bunting-nope'));")" "false"
ok "a non-Bunting scope for a Bunting flag fails closed" "$(tk "echo json_encode($STORE->for('some-string')->active('bunting-on'));")" "false"
tk "Illuminate\Support\Facades\DB::table('bunting_features')->where('name','bunting-appdefined')->delete(); echo 1;" >/dev/null

# ---- Twig + Blade parity ----
ok "guest: twig values" "$(flags '' twig)" "bunting-nav=off/off,bunting-off=off/off,bunting-on=on/on,bunting-pct=off/off,bunting-public=on/on,bunting-nope=off/off"
ok "guest: blade matches twig" "$(flags '' blade)" "$(flags '' twig)"
ok "featureAny / @featureany" "$(curl -sk "$U/bunting/twig" | grep -o '<p id="any">[^<]*' | sed 's/.*>//')|$(curl -sk "$U/bunting/blade" | tr -d ' ' | grep -o '<pid="any">[^<]*' | sed 's/.*>//')" "on/off|on/off"
ok "guest values aren't stored" "$(tk "echo Illuminate\Support\Facades\DB::table('bunting_features')->where('scope','like','%|guest')->count();")" "0"
JA=$(login bunting_alice); JB=$(login bunting_bob); JC=$(login bunting_carol)
ok "alice (staff): twig" "$(flag "$JA" twig bunting-nav)" "on/on"
ok "alice (staff): blade" "$(flag "$JA" blade bunting-nav)" "on/on"
ok "bob: twig/blade" "$(flag "$JB" twig bunting-nav)|$(flag "$JB" blade bunting-nav)" "off/off|off/off"
ok "carol's override: twig/blade" "$(flag "$JC" twig bunting-off)|$(flag "$JC" blade bunting-off)" "on/on|on/on"
ok "logged-in pages: twig and blade agree" "$(flags "$JA" twig)" "$(flags "$JA" blade)"
for e in twig blade; do
  ok "$e requireFeature: on → 200" "$(code "$U/bunting/require-$e?f=bunting-on")" "200"
  ok "$e requireFeature: off → 404" "$(code "$U/bunting/require-$e?f=bunting-off")" "404"
  ok "$e requireFeature: unknown → 404" "$(code "$U/bunting/require-$e?f=bunting-nope")" "404"
  ok "$e requireFeature: override on → 200" "$(code -b "$JC" "$U/bunting/require-$e?f=bunting-off")" "200"
done
ok "route middleware bunting:<on flag> → 200" "$(curl -sk "$U/bunting/route-on")" "ROUTE-ON"
ok "route middleware bunting:<off flag> → 404" "$(code "$U/bunting/route-off")" "404"
ok "route middleware honours overrides" "$(curl -sk -b "$JC" "$U/bunting/route-off")" "ROUTE-OFF"

# ---- URI gating ----
ok "gated URI while off → 404" "$(code "$U/bunting/gated")" "404"
ok "gated wildcard URI while off → 404" "$(code "$U/bunting/beta/page")" "404"
ok "ungated URI unaffected" "$(code "$U/bunting/twig")" "200"
rollout bunting-gate "['mode' => 'on']"
ok "gated URI while on → 200" "$(curl -sk "$U/bunting/gated" | grep -o 'GATED-PAGE')" "GATED-PAGE"
ok "gated pages aren't shared-cacheable" "$(curl -sk -D - -o /dev/null "$U/bunting/gated" | grep -i '^cache-control' | grep -c 'no-store')" "1"
rollout bunting-gate "['mode' => 'rules', 'groupUids' => [CraftCms\Cms\Support\Facades\UserGroups::getGroupByHandle('staff')->uid]]"
ok "gated by group: staff sees it, others 404" "$(code -b "$JA" "$U/bunting/gated")/$(code -b "$JB" "$U/bunting/gated")/$(code "$U/bunting/gated")" "200/404/404"
rollout bunting-gate "['mode' => 'off', 'groupUids' => []]"
ok "gating doesn't touch action requests" "$(code -X POST "$U/actions/bunting/nothing")" "$(code -X POST "$U/actions/nothing-at-all/x")"

# ---- Admin preview ----
# Other agents' runs can end admin sessions, so each section starts with a fresh one.
JAD=$("$H/bin/cp-session")
ok "admin preview: ?bunting-preview=bunting-off:on" "$(flag "$JAD" twig bunting-off '' '?bunting-preview=bunting-off:on')" "on/on"
ok "admin preview sticks for the session (blade too)" "$(flag "$JAD" blade bunting-off)" "on/on"
ok "admin preview never stored" "$(tk "echo Illuminate\Support\Facades\DB::table('bunting_features')->where('name','bunting-off')->where('scope','like','%|user:'.CraftCms\Cms\User\Elements\User::find()->username('admin')->one()->uid)->where('value','true')->count();")" "0"
ok "admin preview opens gated URIs for the admin only" "$(code -b "$JAD" "$U/bunting/gated?bunting-preview=bunting-gate:on")/$(code "$U/bunting/gated")" "200/404"
ok "preview pages aren't shared-cacheable" "$(curl -sk -b "$JAD" -D - -o /dev/null "$U/bunting/twig" | grep -i '^cache-control' | grep -c 'no-store')" "1"
ok "preview of an unknown flag is ignored" "$(flag "$JAD" twig bunting-nope '' '?bunting-preview=bunting-nope:on')" "off/off"
ok "?bunting-preview=clear ends it" "$(flag "$JAD" twig bunting-off '' '?bunting-preview=clear')" "off/off"
ok "people without the permission can't preview" "$(flag "$JB" twig bunting-off '' '?bunting-preview=bunting-off:on')" "off/off"
ok "guests can't preview" "$(flag '' twig bunting-off '' '?bunting-preview=bunting-off:on')" "off/off"

# ---- Stale-flag bookkeeping ----
ok "checks record lastCheckedAt" "$(tk "echo $F->get('bunting-on')->lastCheckedAt ? 'yes' : 'no';")" "yes"
tk "Illuminate\Support\Facades\DB::table('bunting_flags')->where('handle','bunting-private')->update(['lastCheckedAt'=>now()->utc()->subDays(90)]); Illuminate\Support\Facades\Cache::forget('bunting:checked:'.$F->get('bunting-private')->uid); echo 1;" >/dev/null
ok "stale report lists a flag unchecked for 90 days" "$(ddev artisan craft:bunting:stale --days=30 2>/dev/null | grep -c 'bunting-private')" "1"
ddev artisan craft:bunting:stale --days=30 --fail >/dev/null 2>&1; ok "stale --fail exits non-zero" "$?" "1"

# ---- Console set ----
ddev artisan craft:bunting:set bunting-off on >/dev/null 2>&1
ok "console set: mode" "$(check bunting-off --user=bunting_bob)" "on (on)"
ddev artisan craft:bunting:set bunting-off off >/dev/null 2>&1
ok "console set: bad mode refused" "$(ddev artisan craft:bunting:set bunting-off maybe 2>&1 | grep -c 'must be')" "1"
ok "console set: bad percentage refused" "$(ddev artisan craft:bunting:set bunting-pct rules --percentage=101 2>&1 | grep -c 'whole number')" "1"
ok "console purge" "$(ddev artisan craft:bunting:purge bunting-nav 2>&1 | grep -c 'Purged')/$(tk "echo Illuminate\Support\Facades\DB::table('bunting_features')->where('name','bunting-nav')->count();")" "1/0"

# ---- Condition rule ----
RULE='$r=new JustinHoltWeb\Bunting\Conditions\FeatureActiveConditionRule; $r->value='
ENTRY='CraftCms\Cms\Entry\Elements\Entry::find()->one()'
ok "condition rule: on flag matches" "$(tk "$RULE$F->get('bunting-on')->uid; echo json_encode(\$r->matchElement($ENTRY));")" "true"
ok "condition rule: off flag doesn't" "$(tk "$RULE$F->get('bunting-off')->uid; echo json_encode(\$r->matchElement($ENTRY));")" "false"
ok "condition rule: deleted/unknown flag doesn't" "$(tk "$RULE'00000000-0000-0000-0000-000000000000'; echo json_encode(\$r->matchElement($ENTRY));")" "false"
ok "condition rule: element query empties while off" "$(tk "\$c=CraftCms\Cms\Entry\Elements\Entry::createCondition(); $RULE$F->get('bunting-off')->uid; \$c->addConditionRule(\$r); \$q=CraftCms\Cms\Entry\Elements\Entry::find(); \$c->modifyQuery(\$q); echo \$q->count();")" "0"
ok "condition rule: offered in element conditions" "$(tk "echo json_encode(collect(CraftCms\Cms\Entry\Elements\Entry::createCondition()->getSelectableConditionRules())->contains(fn(\$r) => \$r instanceof JustinHoltWeb\Bunting\Conditions\FeatureActiveConditionRule));")" "true"

# ---- GraphQL ----
tk "require 'storage/bunting-gql-fixtures.php';" >/dev/null
# gq <schema> <query> <python expression over the response r / data d / list l>
gq() { curl -sk -H "Authorization: Bearer $(printf 'bunting%s' "$1" | awk '{ s=$0; while (length(s) < 32) s=s "0"; print s }')" -H 'Content-Type: application/json' \
  --data "$(python3 -c 'import json,sys; print(json.dumps({"query": sys.argv[1]}))' "$2")" "$U/actions/graphql/api" \
  | python3 -c 'import json,sys
try: r=json.load(sys.stdin)
except Exception: print("not json"); sys.exit()
if r.get("errors"): print("ERR:" + "; ".join(e.get("message","") for e in r["errors"])); sys.exit()
d=r.get("data") or {}; l=d.get("buntingFlags") or []
print(eval(sys.argv[1]))' "$3"; }
vals='",".join(f["handle"]+"="+str(f["active"]).lower() for f in l)'
ok "gql: public flag resolved for the request (guest)" "$(gq public '{ buntingFlags { handle active } }' "$vals")" "bunting-public=true"
ok "gql: a private flag is never returned, even if the schema names it" "$(gq private '{ buntingFlags { handle active } }' "$vals")" "bunting-public=true"
ok "gql: a schema without flags has no buntingFlags query" "$(gq none '{ buntingFlags { handle } }' 'd')" 'ERR:Cannot query field "buntingFlags" on type "Query".'
ok "gql: handles filter; unknown handles are just absent" "$(gq public '{ buntingFlags(handles: ["bunting-public", "bunting-nope", "bunting-private"]) { handle active } }' "$vals")" "bunting-public=true"
ok "gql: rules aren't part of the type" "$(gq public '{ buntingFlags { handle mode percentage } }' 'd' | grep -c 'Cannot query field \"mode\"')" "1"
ok "gql: a site the schema can't read is refused" "$(gq public '{ buntingFlags(site: "nope") { handle } }' 'd')" "ERR:This schema can’t read the “nope” site."
rollout bunting-public "['mode' => 'off']"
ok "gql: a rollout change shows up at once (never cached)" "$(gq public '{ buntingFlags { handle active } }' "$vals")" "bunting-public=false"
rollout bunting-public "['mode' => 'on']"
ok "gql: … and back" "$(gq public '{ buntingFlags { handle active } }' "$vals")" "bunting-public=true"
ok "gql: making a flag private drops it from schemas" "$(tk "require_once 'storage/bunting-retry.php'; \$f=$F->get('bunting-public'); \$f->public=false; bunting_retry(fn () => $F->saveDefinition(\$f)); echo 1;" >/dev/null; gq public '{ buntingFlags { handle } }' 'd')" 'ERR:Cannot query field "buntingFlags" on type "Query".'
tk "require_once 'storage/bunting-retry.php'; \$f=$F->get('bunting-public'); \$f->public=true; bunting_retry(fn () => $F->saveDefinition(\$f)); echo 1;" >/dev/null

# ---- CP ----
rm -f "$JAD"; JAD=$("$H/bin/cp-session")
cpcode() { code -b "$JAD" "$U/admin/$1"; }
FLAGUID() { tk "echo $F->get('$1')?->uid ?? '';"; }
for p in bunting bunting/new bunting/flags/bunting-nav bunting/audit "bunting/users/$ALICE" "bunting/flags/bunting-nav?checkUser=bunting_alice"; do ok "CP page $p" "$(cpcode "$p")" "200"; done
ok "CP: unknown flag 404s" "$(cpcode bunting/flags/bunting-nope)" "404"
ok "CP: unknown user 404s" "$(cpcode bunting/users/99999999)" "404"
ok "CP list shows the flags" "$(curl -sk -b "$JAD" "$U/admin/bunting" | grep -o 'bunting-[a-z]*' | sort -u | grep -c -E '^bunting-(nav|off|on|pct|gate|public|private)$')" "7"
ok "CP check tool explains" "$(curl -sk -b "$JAD" "$U/admin/bunting/flags/bunting-nav?checkUser=bunting_alice&checkSite=default" | grep -o 'data-reason=\\"[a-z-]*' | sed 's/.*"//')" "group"
ok "CP: create a flag" "$(post "$JAD" bunting/flags/save -d uid= -d name='Bunting CP' -d handle=bunting-cp -d mode=rules -d percentage=30 -d 'uriPatterns=bunting/cp-only')/$(check bunting-cp --user=bunting_alice | grep -c -E 'rollout|no-match')" "302/1"
CPUID=$(FLAGUID bunting-cp)
ok "CP: duplicate handle refused" "$(post "$JAD" bunting/flags/save -d uid= -d name=Dup -d handle=bunting-cp -d mode=on >/dev/null; tk "echo count(array_filter($F->all(), fn(\$f) => \$f->handle === 'bunting-cp'));")" "1"
ok "CP: bad handle refused" "$(post "$JAD" bunting/flags/save -d uid= -d name=Bad -d 'handle=Bad Handle' -d mode=on >/dev/null; tk "echo $F->get('Bad Handle') ? 'saved' : 'refused';")" "refused"
ok "CP: percentage over 100 refused" "$(post "$JAD" bunting/flags/save -d uid="$CPUID" -d name='Bunting CP' -d handle=bunting-cp -d mode=rules -d percentage=101 >/dev/null; tk "echo $F->get('bunting-cp')->percentage;")" "30"
ok "CP: a URI pattern with odd characters refused" "$(post "$JAD" bunting/flags/save -d uid="$CPUID" -d name='Bunting CP' -d handle=bunting-cp -d mode=rules -d 'uriPatterns=a(b|c)' >/dev/null; tk "echo json_encode($F->get('bunting-cp')->uriPatterns);")" '["bunting\/cp-only"]'
ok "CP: an unknown group refused" "$(post "$JAD" bunting/flags/save -d uid="$CPUID" -d name='Bunting CP' -d handle=bunting-cp -d mode=rules -d 'groups[]=not-a-group' >/dev/null; tk "echo json_encode($F->get('bunting-cp')->groupUids);")" "[]"
ok "CP: an unknown site's mode is dropped" "$(post "$JAD" bunting/flags/save -d uid="$CPUID" -d name='Bunting CP' -d handle=bunting-cp -d mode=on -d percentage=30 -d 'siteModes[not-a-site]=off' >/dev/null; tk "echo json_encode($F->get('bunting-cp')->siteModes).'/'.$F->get('bunting-cp')->mode;")" "[]/on"
ok "CP: rename keeps the UID" "$(post "$JAD" bunting/flags/save -d uid="$CPUID" -d name='Bunting CP 2' -d handle=bunting-cp2 -d mode=on -d percentage=30 >/dev/null; FLAGUID bunting-cp2)" "$CPUID"
ok "CP: add an override" "$(post "$JAD" bunting/flags/override -d uid="$CPUID" -d user=bunting_bob -d value=off)/$(check bunting-cp2 --user=bunting_bob)" "302/off (override)"
ok "CP: override for an unknown user refused" "$(post "$JAD" bunting/flags/override -d uid="$CPUID" -d user=nobody_here -d value=on >/dev/null; tk "echo count($F->overrides($F->get('bunting-cp2')));")" "1"
ok "CP: bad override value → 400" "$(post "$JAD" bunting/flags/override -d uid="$CPUID" -d user=bunting_bob -d value=maybe)" "400"
ok "CP: user screen saves overrides" "$(post "$JAD" bunting/users/save -d userId="$BOB" -d "overrides[$CPUID]=inherit" -d "overrides[$(FLAGUID bunting-on)]=off" >/dev/null; check bunting-cp2 --user=bunting_bob)/$(check bunting-on --user=bunting_bob)" "on (on)/off (override)"
post "$JAD" bunting/users/save -d userId="$BOB" -d "overrides[$(FLAGUID bunting-on)]=inherit" >/dev/null
ok "CP: purge" "$(post "$JAD" bunting/flags/purge -d uid="$CPUID")" "302"
ok "audit: who did what" "$(tk "echo Illuminate\Support\Facades\DB::table('bunting_audit')->where('flagUid','$CPUID')->where('userName','admin')->orderBy('id')->pluck('action')->unique()->implode(',');")" "created,rollout,updated,override,purged"
ok "audit page lists them" "$(curl -sk -b "$JAD" "$U/admin/bunting/audit?flag=bunting-cp2" | grep -o 'data-action=\\"[a-z]*' | sed 's/.*"//' | sort -u | tr '\n' ' ')" "created override purged rollout updated "
ok "CP: delete" "$(post "$JAD" bunting/flags/delete -d uid="$CPUID")/$(FLAGUID bunting-cp2)/$(tk "echo Illuminate\Support\Facades\DB::table('bunting_overrides')->count() >= 0 ? Illuminate\Support\Facades\DB::table('bunting_audit')->where('flagUid','$CPUID')->where('action','deleted')->count() : 'x';")" "302//1"
ok "CP: logged-out POST refused (CSRF)" "$(code -X POST -d uid= -d name=X -d handle=bunting-x -d mode=on "$U/admin/bunting/flags/save")" "419"
ok "CP: no CP access → no Bunting pages" "$(code -b "$JB" "$U/admin/bunting" | grep -c -E '302|403')" "1"
ok "CP: no CP access → can't save" "$(post "$JB" bunting/flags/save -d uid= -d name=X -d handle=bunting-x -d mode=on | grep -c -E '302|403|419')/$(FLAGUID bunting-x)" "1/"
ok "user edit page links the Feature flags screen" "$(curl -sk -b "$JAD" "$U/admin/users/$ALICE" | grep -c "bunting\\\\/users\\\\/$ALICE")" "1"

rm -f "$JAD"; JAD=$("$H/bin/cp-session")
# ---- Multisite (Bunting's own `buntingfr` site; MULTISITE=1 adds it under the harness lock) ----
if [ "${MULTISITE:-0}" = 1 ]; then
  "$H/bin/harness-lock" ddev artisan tinker --execute "require 'storage/bunting-multisite-fixtures.php';" 2>&1 | grep -v -E 'WARNING|DEPRECATED|^\s*$' | tail -1
fi
FR=$(tk "echo CraftCms\Cms\Support\Facades\Sites::getSiteByHandle('buntingfr', true)?->uid ?? '';")
if [ -n "$FR" ]; then
  rollout bunting-nav "['mode' => 'off', 'siteModes' => ['$FR' => 'on']]"
  for e in twig blade; do
    ok "multisite $e: off on the default site" "$(flag '' $e bunting-nav)" "off/off"
    ok "multisite $e: on for buntingfr" "$(flag '' $e bunting-nav /buntingfr)" "on/on"
    ok "multisite $e: the page knows its site" "$(curl -sk "$U/buntingfr/bunting/$e" | tr -d ' ' | grep -o '<pid="site">[a-z]*' | sed 's/.*>//')" "buntingfr"
  done
  ok "multisite: console check per site" "$(check bunting-nav --site=buntingfr)|$(check bunting-nav --site=default)" "on (on)|off (off)"
  ok "multisite: stored per site" "$(tk "echo json_encode([$STORE->for(app(JustinHoltWeb\Bunting\Bunting::class)->scope($BOB, 'buntingfr'))->active('bunting-nav'), $STORE->for(app(JustinHoltWeb\Bunting\Bunting::class)->scope($BOB, 'default'))->active('bunting-nav')]);")" "[true,false]"
  ok "multisite: activeFor with an unknown site is off, not an error" "$(tk "echo json_encode(app(JustinHoltWeb\Bunting\Bunting::class)->activeFor('bunting-on', null, 'nope'));")" "false"
  ok "multisite: gated URI per site" "$(rollout bunting-gate "['mode' => 'off', 'siteModes' => ['$FR' => 'on']]"; code "$U/buntingfr/bunting/gated")/$(code "$U/bunting/gated")" "200/404"
  rollout bunting-gate "['mode' => 'off', 'siteModes' => []]"
  rollout bunting-public "['siteModes' => ['$FR' => 'off']]"
  tk "require 'storage/bunting-gql-fixtures.php';" >/dev/null
  ok "multisite gql: site argument" "$(gq fr '{ buntingFlags(site: "buntingfr") { handle active } }' "$vals")" "bunting-public=false"
  ok "multisite gql: a site the schema can't read is refused" "$(gq public '{ buntingFlags(site: "buntingfr") { handle } }' 'd')" "ERR:This schema can’t read the “buntingfr” site."
  ok "multisite CP: per-site modes shown" "$(curl -sk -b "$JAD" "$U/admin/bunting/flags/bunting-nav" | grep -c 'bunting-site-modes')" "1"
  ok "multisite CP: save a site's mode" "$(post "$JAD" bunting/flags/save -d uid="$(FLAGUID bunting-nav)" -d name='New navigation' -d handle=bunting-nav -d mode=off -d "siteModes[$FR]=rules" -d "groups[]=$(tk "echo CraftCms\Cms\Support\Facades\UserGroups::getGroupByHandle('staff')->uid;")" >/dev/null; check bunting-nav --site=buntingfr --user=bunting_alice)" "on (group)"
  rollout bunting-public "['siteModes' => []]"
  if [ "${MULTISITE:-0}" = 1 ]; then
    rollout bunting-nav "['siteModes' => ['$FR' => 'on']]"
    tk "echo json_encode($STORE->for(app(JustinHoltWeb\Bunting\Bunting::class)->scope($BOB, 'buntingfr'))->active('bunting-nav'));" >/dev/null
    ok "multisite: removing the site" "$("$H/bin/harness-lock" ddev artisan tinker --execute "require 'storage/bunting-multisite-remove.php';" 2>/dev/null | tail -1)" "removed"
    ok "multisite: a deleted site leaves no site modes or stored values" "$(tk "echo json_encode($F->get('bunting-nav')->siteModes).'/'.Illuminate\Support\Facades\DB::table('bunting_features')->where('scope','like','site:$FR|%')->count();")" "[]/0"
  fi
else
  echo "- multisite: skipped (no buntingfr site; run with MULTISITE=1)"
fi

# ---- Cleanup ----
# Project config may be locked by another agent's save for a moment: retry.
for i in 1 2 3 4 5 6; do r=$(tk "require 'storage/bunting-cleanup.php';"); [ "$r" = clean ] && break; sleep 5; done
ok "cleanup" "$r" "clean"
rm -rf resources/views/bunting storage/bunting-*.php
rm -f "$JA" "$JB" "$JC" "$JAD"
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
