# Stripe on the new payment settings screen

Draft starting point. Goal: build the new Stripe settings UI on
WooCommerce's experimental payment settings framework. This note is how to run it and where to
start. It is a scaffold, not a finished feature.

STRIPE-1533.

## What this branch adds

- `includes/admin/class-wc-stripe-settings-screen.php`: registers a Stripe page
  (`/settings/stripe`), its field layout (View Config), and a REST route the page loads and saves
  through. Off unless WooCommerce's experimental screen is on, so it is a no-op on a normal install.
- `client/settings-screen/`: the browser entry that registers the entity and its fields, plus a
  per-section folder. Wired into webpack as the `settings-screen` entry.
- Registered in `WC_Stripe::init()` (outside the `is_admin()` block, since the REST record route
  fires on `rest_api_init`), gated on the feature flag, and deferred to `init` so the flag is
  readable when it is checked.

Only the "general" section (enable / title / description) is a worked example. The other sections
are stubs for us to split up. See `client/settings-screen/sections/README.md`.

## How the framework works (from the references)

A gateway registers its own page with the same WordPress APIs the WooCommerce cheque example uses:

1. Load a script from the `wc-settings-screen-wp-admin_init` action (fires only on that page).
2. In that script, `addEntities()` a core-data entity of kind `woo_settings_payments`. Its name is
   the page segment and the record id, so `stripe` is shown at `/settings/stripe` and loads/saves
   at `{baseURL}/stripe`.
3. Register the fields with `registerEntityField` from `@wordpress/editor`.
4. Lay out the form in PHP with the `get_entity_view_config_woo_settings_payments_{name}` filter.

The screen has one level of pages for now, so a sub-page would be a separate entity (for example
`stripe-express-checkout`), not a nested page.

## References (pinned)

- WooCommerce foundation PR, the cheque example and View Config:
  `woocommerce/woocommerce#70007` at `6c04ead5fdd0638d603ebace50f6ce4081efff85`.
- WooPayments PR, the external-plugin pattern and the best template to copy:
  `Automattic/woocommerce-payments#12269` at `6707da7a8f4965892afab1bd87637759c611fc0a`.
- Daniel Mallory's guidance on STRIPE-1533.

Both PRs are open and experimental; the framework targets Woo 11.4 behind a flag and will change.
Flag anything that gets in the way back to Daniel.

## Environment

1. WordPress 7.1 or later.
2. The Gutenberg plugin 24.2 or later, active. `registerEntityField` does nothing without it.
3. WooCommerce built from the `#70007` branch (pinned commit above), not a release.
4. Enable the experimental feature. It is WooCommerce's `experimental-admin-screens` feature flag.
   Turn it on the same way WooCommerce enables experimental features (feature settings or the
   `woocommerce_admin_features` filter); confirm with
   `Automattic\WooCommerce\Admin\Features\Features::is_enabled( 'experimental-admin-screens' )`.
5. This plugin on this branch.

## Run it (verified locally)

Verified on WP 7.1.3 + Gutenberg 24.2.0 + WooCommerce 11.4.0-dev (#70007): `/settings/stripe`
renders the General card (Enable Stripe, Title, Description) and saves to
`woocommerce_stripe_settings`. `registerEntityField` is exported by `@wordpress/editor` at
Gutenberg 24.2, so no dependency bump was needed.

Recipe used (with `wp-env`):

1. Check out WooCommerce `#70007` in a monorepo clone and `pnpm install`. Build what the screen
   needs: `pnpm --filter=@woocommerce/plugin-woocommerce build:admin` (this generates
   `includes/react-admin/feature-config.php`; without it WooCommerce fatals) and
   `pnpm --filter=@woocommerce/plugin-woocommerce build:project:admin-screens`.
2. Build this plugin's assets: `npm run build:webpack` (the `settings-screen` entry writes
   `build/settings-screen.js`, which the PHP enqueues).
3. Bring up a WP 7.1+ env mounting the built WooCommerce, the Gutenberg 24.2.0 plugin, and this
   plugin; activate all three.
4. Enable the flag with a mu-plugin:
   `add_filter( 'woocommerce_admin_features', fn( $f ) => array_merge( $f, [ 'experimental-admin-screens' ] ) );`
5. Open `/wp-admin/admin.php?page=wc-settings-screen-wp-admin&p=/settings/stripe` (the cheque
   example renders at `...&p=/settings/cheque`).

Gotcha: the screen loads and saves over the REST API from the browser. If you see "Unable to load
settings" and the REST requests 404, the `/wp-json/` rewrite is not active. Use plain permalinks
(Settings > Permalinks > Plain) so `apiFetch` falls back to `?rest_route=`, or make pretty
permalinks work in the env.

## Where to start

- Everyone: read the two reference PRs, then pick a section in
  `client/settings-screen/sections/README.md` and add your name.
- Someone: do the environment + build steps above first so it is not a blocker for the rest.
- Keep each section in its own PHP method and (if needed) its own JS file so we do not clash.
