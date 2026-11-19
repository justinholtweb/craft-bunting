---
title: Routes and URIs
slug: routes-and-uris
order: 40
summary: Gate Craft pages by URI, Laravel routes with middleware, and element conditions.
---

## Gated URIs

A flag’s **Gated URIs** (one per line, `*` matches anything) return a 404 on the front end while the flag
is off for the visitor:

```
new-pricing
beta/*
```

Matching ignores case, repeated and trailing slashes, a trailing `/index` and template extensions, and Bunting
also checks the template Craft resolved for template-routed pages — so `/New-Pricing/`, `/new-pricing/index`
and `/new-pricing.twig` are all gated with `new-pricing`. A URL it can’t read (invalid UTF-8) is treated as
gated. Entries are matched on their URI. Action requests and the control panel are never gated.

The gate runs before Craft renders anything, so a gated page’s template never runs.

## Laravel routes

```php
Route::middleware(['craft', 'craft.web', 'bunting:new-nav'])->get('beta', BetaController::class);
Route::middleware('bunting:new-nav,new-checkout')->…   // all must be on
```

The route 404s while the flag is off for the request’s user (or guest) and site.

## Element conditions

Element conditions get a **Feature flag is on** rule (under “Bunting”). It ignores the element and asks
whether the flag is on for the current request, so you can show a field only while a flag is on, limit a
relation field’s sources, or use it in any plugin’s condition builder. It stores the flag’s UID, so renaming
a flag doesn’t break it; a deleted flag counts as off. In element queries, an off flag returns nothing.
