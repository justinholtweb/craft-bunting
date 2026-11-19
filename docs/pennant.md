---
title: Pennant in PHP
slug: pennant
order: 50
summary: Feature::active() and friends, scopes for a user and site, and features your app defines itself.
---

Bunting is a Pennant **store** named `bunting`. With the default setting it’s Pennant’s default store, so
plain Pennant code reads Bunting’s flags:

```php
use Laravel\Pennant\Feature;

Feature::active('new-nav');                  // current site + signed-in user (or guest)
Feature::someAreActive(['a', 'b']);
```

Without the default-store setting, name the store: `Feature::store('bunting')->active('new-nav')`.

## Scopes

Pennant’s default scope is the authenticated Eloquent user. Bunting’s is a **site + user** pair
(`JustinHoltWeb\Bunting\Pennant\Scope`) for the current request. For anyone else:

```php
$bunting = app(\JustinHoltWeb\Bunting\Bunting::class);

Feature::for($bunting->scope($user, 'fr'))->active('new-nav');   // a user on the fr site
Feature::for($bunting->scope(null))->active('new-nav');          // a guest on the current site
$bunting->activeFor('new-nav', $userId, 'fr');                   // same, as a bool
$bunting->explain('new-nav', $bunting->scope($user));            // ['value' => true, 'reason' => 'group']
```

Craft’s user elements aren’t Eloquent models, so pass a Bunting scope rather than a user to
`Feature::for()`. A Bunting flag checked for any other kind of scope is off.

## Your own features

Features your app defines with `Feature::define()` on Bunting’s store keep working — Bunting only
handles the flags it knows, and leaves everything else to Pennant. A Bunting flag with the same handle wins.

`Feature::for($scope)->activate('new-nav')` stores a value for that scope, but Bunting purges stored values
whenever the rollout changes. For something that should last, use a per-user override.

## Queues and Octane

Bunting looks flags up per request and per queued job, so a long-running worker sees CP changes on its
next job. Pennant’s in-memory cache is flushed between Octane requests and queue jobs.
