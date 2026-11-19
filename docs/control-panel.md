---
title: Control panel
slug: control-panel
order: 70
summary: The flags list, a flag's screen, the user screen, the audit log, permissions and settings.
---

## Bunting → Flags

Every flag with its rollout, per-site modes, whether it’s public, and when code last checked it.

A flag’s screen has:

- **Flag** — name, handle, description, Public, gated URIs (read-only while admin changes are off).
- **Rollout** — the mode everywhere, each site’s mode, user groups, percentage.
- **Per-user overrides** — add (username, email or ID) or remove.
- **Check a user** — whether the flag is on for someone (or a guest) on a site, and the rule that decided.
- **Recent changes**, and **Purge stored values** / **Delete flag**.

## Users → (a user) → Feature flags

Every flag’s value for that person on each site, with the reason, and their overrides.

## Bunting → Audit log

Who changed what and when: flags created, changed or deleted (from the CP or a project config apply — those
show as “Console or project config”), rollout changes with before → after, overrides and purges. Filter by
flag; a renamed flag’s history comes with it.

## Permissions

| Permission | Allows |
|---|---|
| Access Bunting | Seeing flags and their rollout |
| Edit flags and their rollout | Creating, editing and deleting flags, purging |
| View the audit log | Bunting → Audit log |
| Preview flags | `?bunting-preview=` on the front end |

Looking people up — the check tool, overrides and a user’s Feature flags screen — needs **Edit flags**
*and* Craft’s **View users**. Without View users, the audit log shows override targets by ID only.

## Settings

- **Make Bunting Pennant’s default store** (on) — see [Pennant in PHP](pennant).
- **Percentage rollouts for guests** (off) — see [Flags and rollouts](flags-and-rollouts).
- **Sidebar label** and **Sidebar icon**.

## Stale flags

“Last checked” is updated (at most hourly) whenever code checks a flag. Flags nothing has checked in a
while are candidates for removal:

```bash
php artisan craft:bunting:stale --days=30 [--fail]
```
