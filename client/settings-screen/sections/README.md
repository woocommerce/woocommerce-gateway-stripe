# Settings screen sections

Draft starting point for the meetup. One section is one card on the Stripe page and one owner.

## How a section is split

- PHP owns the fields and the save: each section is a method in
  `includes/admin/class-wc-stripe-settings-screen.php` (`get_sections()` plus a
  `get_<section>_fields()` method), and its save mapping in `handle_request()`.
- JS (this folder) owns the custom controls, only when a plain field control is not enough.
  Add a file per section here that exports the `Edit` components to merge into that section's
  fields in `../index.js`.

## Sections and owners

| Section | Page card | Owner | Status |
| --- | --- | --- | --- |
| general | General | (worked example) | enable / title / description done |
| payment-methods | Payment methods | ____ | stub |
| express-checkout | Express checkout | ____ | stub |
| advanced | Advanced | ____ | stub |

## Start here

1. Pick a section above and put your name in the Owner column.
2. Add its fields in the matching `get_<section>_fields()` method in the PHP class.
3. Map those fields in `handle_request()` so they load and save.
4. If a field needs a control beyond the plain types, add `<section>.js` here and merge its
   `Edit` component in `../index.js`.
