---
title: Console commands
slug: console-commands
order: 80
summary: List, flip, check, purge and find stale flags from the command line.
---

```bash
php artisan craft:bunting:list
```
Every flag with its mode, per-site modes, groups, percentage and last check.

```bash
php artisan craft:bunting:set new-nav off
php artisan craft:bunting:set new-nav on --site=fr
php artisan craft:bunting:set new-nav inherit --site=fr
php artisan craft:bunting:set new-nav rules --percentage=25
```
Flip a flag from a deploy script or during an incident. Audited (as the console).

```bash
php artisan craft:bunting:check new-nav --user=ada --site=fr
# on (group)
```
Whether a flag is on for a user (username, email or ID; default a guest) on a site, and why:
`override`, `on`, `off`, `group`, `rollout` or `no-match`.

```bash
php artisan craft:bunting:purge [new-nav …]
```
Drop stored values (all flags without arguments). Audited.

```bash
php artisan craft:bunting:stale --days=30 [--fail]
```
Flags not checked in that many days; `--fail` exits non-zero when there are any, for CI.
