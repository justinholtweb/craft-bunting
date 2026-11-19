---
title: Twig and Blade
slug: templates
order: 30
summary: feature(), @feature, the bunting global and requireFeature, with the same results in both engines.
---

Every template feature works in Twig and Blade, and returns the same thing in both.

| | Twig | Blade |
|---|---|---|
| Is a flag on? | `feature('new-nav')` | `@feature('new-nav') … @else … @endfeature` |
| Is any on? | `featureAny(['a', 'b'])` | `@featureany(['a', 'b']) … @endfeatureany` |
| Bunting’s store, always | `bunting.active('new-nav')` | `$bunting->active('new-nav')` |
| For someone else | `bunting.activeFor('new-nav', user, 'fr')` | `$bunting->activeFor('new-nav', $user, 'fr')` |
| All Bunting flags | `bunting.all()` | `$bunting->all()` |
| 404 unless on | `{% requireFeature 'new-nav' %}` | `@requireFeature('new-nav')` |

`feature()` and `@feature` (Pennant’s own directive) read Pennant’s **default store**. That’s Bunting’s
unless you turn off **Make Bunting Pennant’s default store**; `bunting.active()` always reads Bunting.

`requireFeature` takes one handle or a list (all must be on). Naming no flag counts as off.

`activeFor()` takes a user, a user ID or `null` (a guest), and a site handle or `null` (the current site).
An unknown user or site counts as off instead of erroring.

## Caching

A flag’s value depends on who’s looking. Don’t put flag checks inside `{% cache %}` blocks or pages a
static cache shares between visitors, unless the flag is the same for everyone (on or off for the whole site).
Pages gated by a flag, and pages someone is previewing, are sent with `Cache-Control: private, no-store`.
