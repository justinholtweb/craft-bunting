---
title: GraphQL
slug: graphql
order: 60
summary: Resolved flag values for headless front ends — public flags only, per-schema, never cached.
---

Headless front ends often need to know which features to show. Bunting adds a `buntingFlags` query:

```graphql
{
  buntingFlags(handles: ["new-nav", "new-checkout"], site: "fr") {
    handle
    active
  }
}
```

- **Only public flags.** Tick **Public** on a flag to offer it to GraphQL schemas. Each public flag is its
  own schema permission (GraphQL → Schemas → “Bunting flags” → “Read the “new-nav” flag”). A schema without
  any gets no `buntingFlags` query at all.
- **Only the answer.** Each flag returns its handle and whether it’s on — for the token’s or session’s user,
  or a guest. Rules, groups, percentages, overrides and users are never exposed, and there’s no argument to
  ask about someone else.
- **Sites.** `site` defaults to the current site and must be one the schema can read.
- **Quiet about the rest.** Flags the schema can’t read, private flags and unknown handles are simply left
  out of the list, so a schema can’t probe for handles.
- **Never cached.** A query that reads `buntingFlags` skips Craft’s GraphQL cache, so a flipped flag shows
  up at once and one user’s values are never served to another.
- Making a flag private removes it from every schema immediately.
