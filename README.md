# Bunting

Feature flags for Craft on Laravel Pennant — flip them in the CP, scope them to sites, user groups, users or a percentage.

> [!WARNING]
> **Craft 6 only, and in beta.** Bunting is a native **Craft CMS 6** plugin. Craft 6 is still in beta,
> and so is Bunting (`6.0.0-beta.1`). Try it on development and staging sites first and **use caution on
> production**: keep backups, and expect changes between betas. It does not run on Craft 5.

Docs: [justinholt.com/plugins/craft-bunting](https://justinholt.com/plugins/craft-bunting/docs/). See [PLAN.md](PLAN.md).

## Requirements

- Craft CMS 6.0.0-beta.1 or later (native Craft 6 plugin, no Yii 2 adapter)
- PHP 8.5
- [Laravel Pennant](https://laravel.com/docs/pennant) 1.18+ (installed with the plugin). Bunting brings its own
  Pennant store and table (`bunting_features`); you don't publish or run Pennant's own migration.
- User groups need Craft Pro; without them, everything except group targeting works.

## How it works

A **flag** has a handle (what code checks), a name, a description, and optionally **gated URIs** and a
**Public** switch for GraphQL. Those live in project config, so flags deploy with the code that uses them.

Its **rollout** lives in the database, so it can be flipped on production even with `allowAdminChanges` off:

| Mode | Who gets the flag |
|---|---|
| **Off** | No one |
| **On for everyone** | Everyone |
| **By rules** | Members of the chosen user groups, then a percentage of everyone else who’s signed in |

Each **site** can have its own mode — on for one site, off for another. A **per-user override** (on or off)
beats every rule on every site. Percentage rollouts are **sticky**: each person has a fixed place per flag,
so raising the percentage only adds people. Guests aren’t part of a percentage rollout unless you turn on
the visitor cookie (Settings → Bunting).

Under the hood Bunting is a Pennant **store** (`bunting`): it resolves its flags for the current site and
user, Pennant stores each signed-in user’s value, and Bunting purges them whenever a rollout, override or
group membership changes.

## Templates: Twig and Blade

```twig
{% if feature('new-nav') %}…{% endif %}
{% if featureAny(['new-nav', 'new-footer']) %}…{% endif %}
{% if bunting.active('new-nav') %}…{% endif %}
{% if bunting.activeFor('new-nav', currentUser, 'fr') %}…{% endif %}
{% requireFeature 'new-checkout' %}   {# 404 while off #}
```

```blade
@feature('new-nav') … @else … @endfeature
@featureany(['new-nav', 'new-footer']) … @endfeatureany
@if ($bunting->active('new-nav')) … @endif
@requireFeature('new-checkout')
```

`feature()` / `@feature` read Pennant’s default store, which is Bunting’s unless you switch that off.

## PHP, routes and URIs

```php
use Laravel\Pennant\Feature;

Feature::active('new-nav');                                  // current site + user (or guest)
Feature::for(app(\JustinHoltWeb\Bunting\Bunting::class)->scope($user, 'fr'))->active('new-nav');

Route::get('beta', BetaController::class)->middleware('bunting:new-nav');   // 404 while off
```

Gated URIs (per flag, `*` wildcards) return a 404 on the front end while the flag is off for the visitor.
Every spelling that reaches the same template is covered.

Element conditions get a **Feature flag is on** rule, so field layouts, sources and other plugins’
conditions can depend on a flag.

## Previews

People with the **Preview flags** permission can add `?bunting-preview=new-nav:on,old-footer:off` to any
front-end URL to see the site with those flags, for their own session only (`?bunting-preview=clear` stops).
Previews are never stored.

## Control panel

**Bunting → Flags**: every flag, its rollout per site and when code last checked it. Each flag’s screen has its
rollout, per-user overrides, a **Check a user** tool (on or off, and why) and recent changes. **Bunting → Audit
log** records who changed what — definitions (including project config applies), rollouts, overrides and purges.
Users also get a **Feature flags** screen listing every flag’s value for them on each site.

Permissions: **Access Bunting**, **Edit flags and their rollout**, **View the audit log**, **Preview flags**.
Looking people up (check tool, overrides, the user screen) also needs Craft’s **View users**.

## GraphQL

Headless front ends can read flag values with `buntingFlags`:

```graphql
{ buntingFlags(handles: ["new-nav"], site: "fr") { handle active } }
```

Only flags marked **Public** can be offered to a schema (one permission per flag), and only their resolved
value for the requesting user (or a guest) is returned — never rules, groups, percentages or users. These
queries are never served from Craft’s GraphQL cache.

## Console

```bash
php artisan craft:bunting:list
php artisan craft:bunting:set new-nav on [--site=fr] [--percentage=25]   # off | on | rules | inherit (with --site)
php artisan craft:bunting:check new-nav --user=ada --site=fr               # on (group)
php artisan craft:bunting:purge [flags…]
php artisan craft:bunting:stale --days=30 [--fail]
```

## Development

```bash
composer install
composer test        # Pest
composer phpstan     # Larastan level 6
composer check-cs    # Pint
tests/Harness/check.sh                  # live checks in the Craft 6 harness
MULTISITE=1 tests/Harness/check.sh      # …plus a temporary `buntingfr` site
```

Tested in the Craft 6 harness at `~/Sites/plugin-testing-craft6` (`bin/plugin-wire craft-bunting --install`).

## License

Free, under the [Craft License](LICENSE.md).
