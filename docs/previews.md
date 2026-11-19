---
title: Previews
slug: previews
order: 55
summary: See the site with a flag on or off, for your own session only.
---

Anyone with the **Preview flags** permission (admins always) can add a query parameter to any
front-end URL:

```
https://example.com/?bunting-preview=new-nav:on
https://example.com/pricing?bunting-preview=new-nav:on,old-footer:off
https://example.com/?bunting-preview=clear
```

The preview sticks to your session until you clear it or sign out. It applies to your own requests only,
is never stored in Pennant, and opens gated URIs for you. Unknown flags are ignored; people without the
permission (and guests) can’t preview.

Previewed pages are sent with `Cache-Control: private, no-store`. GraphQL requests don’t use previews.
