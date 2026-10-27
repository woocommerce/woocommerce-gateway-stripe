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
  per-section folder. Not wired into webpack yet (see "Build").
- Wired in `WC_Stripe::init()` behind the feature flag.

Only the "general" section (enable / title / description) is a worked example. The other sections
are stubs for us to split up. See `client/settings-screen/sections/README.md`.

## How the framework works (from the references)

A gateway registers its own page with the same WordPress APIs the WooCommerce cheque example uses:

1. Load a script from the `wc-payment-settings-wp-admin_init` action (fires only on that page).
2. In that script, `addEntities()` a core-data entity of kind `woo_settings_payments`. Its name is
   the page segment and the record id, so `stripe` is shown at `/settings/stripe` and loads/saves
   at `{baseURL}/stripe`.
3. Register the fields with `registerEntityField` from `@wordpress/editor`.
4. Lay out the form in PHP with the `get_entity_view_config_woo_settings_payments_{name}` filter.

The screen has one level of pages for now, so a sub-page would be a separate entity (for example
`stripe-express-checkout`), not a nested page.

## References (pinned)

- WooCommerce foundation PR, the cheque example and View Config:
  `woocommerce/woocommerce#70007` at `f75a874127fc94447cb7be867112b74ad2977c00`.
- WooPayments PR, the external-plugin pattern and the best template to copy:
  `Automattic/woocommerce-payments#12269` at `c9972269841e2efc1c0112e3f84f486949755a6b`.
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

## Build

The client entry is not in webpack yet, on purpose: `registerEntityField` must be exported by the
`@wordpress/editor` version in the env (Gutenberg 24.2+), which is not guaranteed by the version
this plugin currently pins. First step:

1. Stand up the environment above and confirm the WooCommerce cheque page renders at
   `/wp-admin/admin.php?page=wc-payment-settings-wp-admin&p=/settings/cheque`.
2. Add `settings-screen: './client/settings-screen/index.js'` to the webpack entries and build
   (`npm run build:webpack`). The PHP enqueues `build/settings-screen.js`.
3. Open `...&p=/settings/stripe`. You should see the General card with the three fields, saving to
   `woocommerce_stripe_settings`.

If `registerEntityField` is missing from `@wordpress/editor`, that is the first thing to resolve
(bump the dependency to match the env, or import it the way #12269 ends up doing), and worth
flagging to Daniel.

## Where to start

- Everyone: read the two reference PRs, then pick a section in
  `client/settings-screen/sections/README.md` and add your name.
- Someone: do the environment + build steps above first so it is not a blocker for the rest.
- Keep each section in its own PHP method and (if needed) its own JS file so we do not clash.
