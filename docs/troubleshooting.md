---
title: Troubleshooting
slug: troubleshooting
order: 100
summary: A flag isn't on when you expect, a page isn't gated, or the CP is read-only.
---

**A flag is off for someone who should have it.**
Use **Check a user** on the flag’s screen, or `php artisan craft:bunting:check <flag> --user=<name> --site=<handle>`.
It tells you the deciding rule: an override, the site’s mode, a group, the percentage, or no match. Check the
*site’s* mode as well as the flag’s — a site’s own mode wins.

**`feature()` / `@feature` disagree with `bunting.active()`.**
Bunting isn’t Pennant’s default store (Settings → Bunting → Make Bunting Pennant’s default store). `bunting.active()` always reads Bunting.

**A page is still served while the flag is off.**
Gated URIs apply to front-end requests only, not to action URLs or the control panel. Check the pattern is
the site-relative URI (no domain, no site base path), and that a static or template cache isn’t serving an
old copy.

**I can’t edit a flag’s name or handle.**
`allowAdminChanges` is off on this environment. Change the definition on a development environment and
deploy project config; the rollout can be changed anywhere.

**`Feature::for($user)` throws “Unable to serialize the feature scope”.**
Craft users aren’t Eloquent models. Use `Feature::for($bunting->scope($user))` — see [Pennant in PHP](pennant).

**GraphQL has no `buntingFlags` query.**
The schema has no flag permissions: mark the flag **Public**, then tick it under the schema’s “Bunting flags”.
