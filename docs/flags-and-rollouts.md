---
title: Flags and rollouts
slug: flags-and-rollouts
order: 20
summary: Modes, per-site rollouts, user groups, sticky percentages, overrides, and the order Bunting decides in.
---

## What a flag is

| Part | Where it lives | Notes |
|---|---|---|
| Handle, name, description | Project config (`bunting.flags.<uid>`) | Deploys with your code |
| Gated URIs, Public | Project config | See [Routes and URIs](routes-and-uris) and [GraphQL](graphql) |
| Mode, per-site modes, groups, percentage | Database | Can be changed on production with `allowAdminChanges` off |
| Per-user overrides | Database | |

When admin changes are disallowed, a flag’s name, handle and URIs are read-only in the CP, and flags
can’t be created or deleted there; their rollout can still be changed.

Handles are lowercase letters, numbers, dashes, dots and underscores, starting with a letter
(`new-nav`, `checkout.v2`). Renaming a flag keeps its UID, so percentage buckets and conditions that use it
don’t change.

## Modes

- **Off** — off for everyone (overrides still apply).
- **On for everyone** — on for everyone, signed in or not.
- **By rules** — on for members of the chosen **user groups**, then for the chosen **percentage** of
  everyone else who’s signed in. Everyone else: off.

### Per site

Each site can follow the flag or have its own mode. On a multi-site install that’s the natural way to
launch a feature in one market first: the flag **Off**, and the `fr` site **On for everyone** — or **By rules**
with a percentage just there.

## How Bunting decides

For a site and a user (or a guest), in order:

1. **An admin preview** for that person’s own request (see [Previews](previews)).
2. **A per-user override**, on or off, on every site.
3. **The site’s mode**, or the flag’s: off → off, on → on.
4. **By rules**: on if the user is in one of the flag’s groups; else on if they’re inside the percentage.
5. Otherwise off.

Unknown flags are always off, as is anything Bunting can’t work out.

## Percentages are sticky

Each person gets a fixed place (0–99.99) per flag, from the flag’s UID and the user’s UID. They’re on
when their place is below the percentage, so:

- the same people stay in from request to request and device to device;
- raising the percentage only adds people; lowering it only removes them;
- different flags reach different people.

Guests have no stable identity, so they’re outside percentage rollouts unless you turn on
**Percentage rollouts for guests** in the settings. That sets a random first-party cookie
(`bunting_visitor`, one year, no personal data) to place them. Check your cookie policy first.

## Overrides

Add one from the flag’s screen (username, email or ID) or from the user’s **Feature flags** screen.
An override beats every rule, on every site. Both need **Edit flags** and Craft’s **View users**.

## Stored values

Pennant stores each signed-in user’s resolved value (`bunting_features`). Bunting purges them whenever
a flag’s rollout changes, a user gets an override or changes groups, and when a site, user group or user is
deleted, so stored values never go stale. Guests’ values are worked out on each request and never stored.
**Purge stored values** on a flag (or `craft:bunting:purge`) clears them by hand — needed only for values
you set yourself with Pennant’s `activate()`.
