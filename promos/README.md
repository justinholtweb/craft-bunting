# Plugin Store promo images

Marketing images for the Bunting listing on the Craft Plugin Store, in the same theme as
[justinholt.com/plugins/craft-bunting](https://justinholt.com/plugins/craft-bunting) (sage `#A1BA84`, from the icon).

```bash
./build.sh          # all slides
./build.sh "2 5"    # just slides 2 and 5
```

Output: `out/bunting-promo-N.jpg`, 1920×1080 (rendered at 2× in headless Chrome, downsampled).
**JPEG, never PNG.** `fonts.css` is generated and gitignored.

The cover and the closing slide carry the Craft 6 beta caution, and every footer says
"Craft CMS 6 (beta)" — keep it there until Craft 6 and Bunting leave beta.

The watermark is the bunting's **outline** (`assets/watermark.svg`, the stroked `resources/icon-mask.svg` path).

| # | Slide |
| --- | --- |
| 1 | Cover — free, Craft 6 beta caution |
| 2 | One flag, every engine (Twig, Blade, PHP, routes) |
| 3 | Rollouts — staff, percentage, override, one site, everyone |
| 4 | Scopes — sites, groups, people; flippable on production |
| 5 | On or off, and why — console check, set, stale |
| 6 | GraphQL — the answer, not the rules |
| 7 | Closing — summary, beta caution |
