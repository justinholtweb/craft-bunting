---
title: Installation
slug: installation
order: 10
summary: Requirements, the Craft 6 beta caution, install, and your first flag.
---

> **Craft 6 only, and still in beta.** Bunting is a native **Craft CMS 6** plugin. Craft 6 is
> still in beta, and so is Bunting (`6.0.0-beta.1`). Try it on development and staging sites
> first, and **use caution before running it on a production site** — keep backups, and expect
> things to change between betas. It does not run on Craft 5.

## Requirements

- Craft CMS 6.0.0-beta.1 or later
- PHP 8.5
- Laravel Pennant 1.18 or later — Composer installs it with the plugin
- Craft Pro for user-group targeting (everything else works on Solo)

## Install

```bash
composer require justinholtweb/craft-bunting
php artisan craft:plugin:install bunting
```

Installing creates four tables: `bunting_flags`, `bunting_overrides`, `bunting_audit` and
`bunting_features` (Pennant’s schema, under Bunting’s name). You don’t publish or run Pennant’s own
`features` migration; if your app already uses Pennant, its store and table are left alone.

If your project config already defines flags (for example, installing on another environment), they’re
picked up on install. Their rollout starts **off** on each environment.

## Your first flag

1. **Bunting → Flags → New flag**. Give it a name and a handle, say `new-nav`.
2. Pick a rollout: **On for everyone**, or **By rules** with a user group and a percentage.
3. Check it in a template:

```twig
{% if feature('new-nav') %}
  {% include '_nav/new' %}
{% else %}
  {% include '_nav/old' %}
{% endif %}
```

```blade
@feature('new-nav')
  @include('_nav.new')
@else
  @include('_nav.old')
@endfeature
```

Use **Check a user** on the flag’s screen to see whether it’s on for someone, and why.
