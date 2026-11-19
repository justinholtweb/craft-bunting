# Bunting — plan

Feature flags for Craft on Laravel Pennant — flip them in the CP, scope them to users, groups, sites or a percentage.

- Package: `justinholtweb/craft-bunting` · handle `bunting` · namespace `JustinHoltWeb\Bunting`
- Editions: single edition · pricing: Free (Craft License)
- Laravel integration: laravel/pennant (1.28 in the harness), Laravel 13, database driver under Bunting's own table.
- Craft 6 only, native Laravel (no `craftcms/yii2-adapter`, ever). Craft 6 is in beta: use caution on production.

## Status (2026-11-18): ready for 6.0.0-beta.1

All four phases done, plus GraphQL, multisite, Twig/Blade parity, icon and docs.

1. **Flags in the CP.** ✅ Definitions (handle, name, description, gated URIs, public) in project config
   (`bunting.flags.<uid>`), mirrored into `bunting_flags`; rollout (off/on/rules, per-site modes, groups,
   sticky percentage) in the database so it can be flipped with `allowAdminChanges` off. Pennant store `bunting`
   (`BuntingDriver` wrapping Pennant's `DatabaseDriver` on `bunting_features`), default store unless switched off.
   Pennant scope = site + user (`Pennant\Scope`), resolved from Craft's user, not the auth guard's Eloquent user.
2. **Twig + PHP.** ✅ `feature()`, `featureAny()`, `{% requireFeature %}`, `bunting` global; Blade `@feature`
   (Pennant's), `@featureany`, `@requireFeature`, `$bunting`; `Feature::active()` for app code; `bunting:<flag>`
   route middleware; gated URIs on front-end requests (path normalised + resolved-template check, fail closed);
   condition rule “Feature flag is on” for every element condition.
3. **Overrides.** ✅ Per-user overrides from the flag screen and a “Feature flags” screen on the user edit page;
   `?bunting-preview=` for people with “Preview flags” (session-only, never stored).
4. **Cleanup.** ✅ Last-checked tracking (hourly), stale report (`craft:bunting:stale`), purge (CP + console).
   Stored values purged on rollout/override/group changes and site/group/user deletion.

Also: audit log (who changed what, incl. project-config applies), check-a-user tool, `craft:bunting:list|set|check`,
GraphQL `buntingFlags` (public flags only, one schema permission per flag, resolved for the requesting user,
never cached), sidebar label/icon settings, `lang/en/bunting.php`.

Tests: 31 Pest; `tests/Harness/check.sh` 151 live checks with `MULTISITE=1` (Bunting's own `buntingfr` site,
added and removed under `bin/harness-lock`), 133 without.

## Known limits / unverified

- CP flag/audit/user screens are server-rendered Twig in Craft's fragment (“bridged”) screen; the list uses
  Craft's `Ui` table. They work and are styled, but aren't native Vue pages.
- `allowAdminChanges = false` behaviour (definitions read-only, rollout editable) is implemented but not
  exercised in the harness (it's a shared general-config change).
- Guest percentage rollout cookie is covered by unit tests only (the harness run keeps the setting off).
- Pennant's `Feature::for($craftUser)` can't work (Craft users aren't Eloquent); documented: pass `Bunting::scope()`.

## Questions for Justin

1. Flags split: definitions in project config, rollout in the DB (so production can flip them with
   admin changes off). OK, or do you want everything in project config (deployable, but not flippable on prod)?
2. Bunting makes itself Pennant's **default** store by default (so `@feature`/`Feature::active()` just work).
   Keep that default, or default it off for apps that already use Pennant?
3. Guest percentage rollouts use an opt-in first-party cookie (`bunting_visitor`). Fine as opt-in, or drop it?
4. The CP edit screens are Twig in Craft's bridged fragment screen. Worth porting to native Vue/`Ui` pages
   before 6.0.0 stable, once core documents plugin Inertia pages?
5. Marketing site accent: sage `#A1BA84` from the icon (legible on the dark background). OK?
6. Free under the Craft License (unchanged). Confirm it stays free.

## Definition of done (every phase)
`bin/port-check craft-bunting` green · Pest suite green · Pint + Larastan level 6 clean · installs, uninstalls and
reinstalls cleanly in the harness · CP pages smoke-tested with `bin/cp-session`.
