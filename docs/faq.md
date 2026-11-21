---
title: FAQ
slug: faq
order: 90
summary: Craft 6 beta, project config, Pennant, multisite, guests and caching.
---

**Does Bunting work on Craft 5?**
No. It’s a native Craft 6 plugin. Craft 6 is in beta, and so is Bunting — use caution on production.

**Why are flags split between project config and the database?**
So a flag ships with the code that checks it, but can still be switched off on production in a hurry,
where admin changes are usually disallowed.

**I already use Pennant. Will Bunting take over?**
Bunting becomes the default store unless you turn that off, and features you define yourself keep working on
it. If you’d rather keep your own default store, turn the setting off and use `Feature::store('bunting')` or
`bunting.active()` for Bunting’s flags. Bunting never touches Pennant’s `features` table.

**How do I launch on one site first?**
Set the flag to **Off**, and the site to **On for everyone** (or **By rules**). See [Flags and rollouts](flags-and-rollouts).

**Are percentage rollouts random each visit?**
No — each person has a fixed place per flag, so they see the same thing every time, and raising the percentage
only adds people.

**Do guests count in a percentage rollout?**
Only with **Percentage rollouts for guests** on, which uses a first-party cookie.

**Can my front end ask whether a flag is on for another user over GraphQL?**
No. `buntingFlags` answers for the requesting user (or a guest) only, and only for public flags.

**What happens when I delete a flag?**
Code that checks it sees it as off. Condition rules that use it stop matching. Its audit history stays.

## Bunting or Wink?

They overlap in one place: both can show something to a percentage of visitors, and keep showing
each visitor the same thing.

- **[Wink](https://craft-wink.com)** runs A/B experiments on Craft 5. It splits visitors between
  variants, tracks conversion goals and reports which variant won, with statistical significance.
- **Bunting** is a feature flag on Craft 6. A flag is on, off, or on for chosen users, groups,
  sites or a percentage. It doesn't track conversions or pick a winner.

Use Bunting to roll a change out gradually or switch it off quickly, and an experiment when you
need to know whether the change worked.
