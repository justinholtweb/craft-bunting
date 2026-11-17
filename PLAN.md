# Bunting — plan

Feature flags for Craft on Laravel Pennant — flip them in the CP, scope them to users, groups, sites or a percentage.

- Package: `justinholtweb/craft-bunting` · handle `bunting` · namespace `JustinHoltWeb\Bunting`
- Editions: single edition · pricing: Free (provisional)
- Laravel integration: laravel/pennant — Pennant v1.28, Laravel 13 compatible, database driver.
- Craft 6 only, native Laravel (no `craftcms/yii2-adapter`, ever). Follow
  `~/Sites/plugin-testing-craft6/porting/COOKBOOK.md` for Craft 6 idioms.

## Phases
1. **Flags in the CP.** Define flags (handle, description, default) in project config; Pennant database driver
   stores per-scope resolutions. Scopes: global, site, user, user group, percentage rollout (sticky per user).
2. **Twig + PHP.** `{% if feature('new-nav') %}`, `craft.bunting.active('x')`, `Feature::active()` keeps working
   for app code; condition-builder rule "Feature is active" for other plugins' conditions.
3. **Overrides.** Per-user overrides from the user edit screen; preview-as query param for admins.
4. **Cleanup.** Stale-flag report (never checked in N days), purge resolutions.

## Risks / open questions
- Pennant's default scope is the authenticated Eloquent user — map to Craft's user identity.

## Definition of done (every phase)
`bin/port-check craft-bunting` green · Pest suite green · Pint + Larastan level 6 clean · installs, uninstalls and
reinstalls cleanly in the harness · CP pages smoke-tested with `bin/cp-session`.
