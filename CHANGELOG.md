# Release Notes for Bunting

## 6.0.0-beta.2 - 2026-11-21

### Changed
- The documentation link (Plugin Store and README) now points at the docs on justinholt.com.
- The docs FAQ now compares Bunting with Wink.

## 6.0.0-beta.1 - 2026-11-18

> Bunting is a native **Craft CMS 6** plugin. Craft 6 is in beta, and so is Bunting: use caution on production.

### Added
- Feature flags on Laravel Pennant: a `bunting` Pennant store (Pennant’s default unless switched off) on Bunting’s own `bunting_features` table.
- Flags defined in project config (handle, name, description, gated URIs, public); rollout kept in the database so flags can be flipped where admin changes are disallowed.
- Rollout modes off, on and by rules, for the whole flag and per site; user-group targeting; sticky percentage rollouts; per-user overrides.
- Optional percentage rollouts for guests, bucketed by a first-party visitor cookie.
- Twig `feature()`, `featureAny()`, `{% requireFeature %}` and the `bunting` global; Blade `@feature` / `@featureany` (Pennant’s), `@requireFeature` and `$bunting`.
- `bunting:<flag>` route middleware and gated URIs (404 while off), normalised so no spelling of a URL reaches a gated template.
- Admin previews with `?bunting-preview=`, for the previewer’s own session only.
- Control panel section: flags, per-site modes, groups, percentage, overrides, a check-a-user tool, recent changes; an audit log; a Feature flags screen on users.
- Permissions: edit flags, view the audit log, preview flags. Looking people up also needs Craft’s “View users”.
- GraphQL `buntingFlags`: resolved values of public flags, one schema permission per flag, never cached.
- “Feature flag is on” rule for element conditions.
- Console: `craft:bunting:list`, `set`, `check`, `purge`, `stale`.
- Stored values are purged when a rollout, override or user’s groups change, and when a site, group or user is deleted.
- Sidebar label and icon settings.
