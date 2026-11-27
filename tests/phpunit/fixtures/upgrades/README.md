# Upgrade preservation fixtures

`WC_Stripe_Upgrade_Preservation_Test` runs the real gateway constructor,
`WC_Stripe::install()`, update manager, migrations, API client and settings
controller against historical stored settings. Only HTTP is faked. The normal
PHPUnit suite discovers it automatically; no separate CI job is needed.

```sh
npm run test:php -- --filter WC_Stripe_Upgrade_Preservation_Test
```

## Coverage

The twelve profiles cover stored states from 10.7.0, 10.9.1 and 11.0.1, including
explicit opt-outs, empty selections, absent opt-in flags, PMC eligibility changes,
and stale local mirrors of an active Stripe payment method configuration (PMC).
Live and test mode cover active PMC, newly eligible PMC, unavailable PMC and stale
mirrors. Preferences include payment methods, ordering, express button locations
and appearance, capture, saved cards, OC/AP and custom refund-email content.

Each profile checks the upgrade, a settings GET with fresh request objects, a
normal subsequent request and a forced rerun with migration flags retained. The
10.7.0 profile also opts out after the historical OC/AP rollout, then reruns the
upgrade to ensure the opt-out survives. Three negative tests inject an OC reset,
a button-location reset and a PMC write during a forced rerun; the harness must
reject each mutation with a diagnostic identifying the setting or HTTP stage.

The database snapshot includes all `wc_stripe_*`, `woocommerce_stripe_*` and
`woocommerce_wc_stripe_*` options plus `woocommerce_gateway_order`. Only named
migration/notice/monitoring options are excluded. Cache rows have a separate
namespace. Every unexpected HTTP request fails, even when production code catches
the HTTP error. Every write must match an expected method, mode, path and payload.

## Where each expectation belongs

- `stores/10-7-0.php`, `10-9-1.php` and `11-0-1.php` contain independent,
  hand-built historical inputs: stored options, remote Stripe configuration and
  merchant-visible values. They contain no upgrade expectations and do not import
  another release's fixture or current production defaults.
- `cases.php` explicitly selects a source store, input overrides and the named
  changes expected at `upgrade` and `first_read`. Overrides replace whole values,
  so an empty selection stays empty. `unset_settings` models absent keys.
- `../../helpers/upgrade-changes/` has one plain helper per intentional behavior:
  OC/AP rollout, Link locations, webhook replacement, PMC eligibility retry and
  PMC mirror refresh. They return exact changes; they neither run migrations nor
  decide whether a case is eligible for an exception.
- The test runner executes the real lifecycle and checks everything against the
  declarations. Its default is no changes. Subsequent requests and forced reruns
  always use that default; an optional `opt_out` step explicitly names the
  merchant edit to make before another rerun.

For example, the `10.7.0 opt-outs` case lists OC/AP rollout, Link locations and
webhook replacement together. It therefore exercises a skipped-release upgrade
without putting a special `10.7.0` branch in the runner. PMC retry cases separately
declare the upgrade's eligibility reset and the first read's result. Expected
changes are selected explicitly, never inferred from a version number: some
migrations depend on stored state instead.

The source fixtures were checked against the tagged
`includes/admin/stripe-settings.php`, account and payment-method-configuration
classes, express checkout helper and `get_upe_ordered_payment_method_ids()`.
The 11.0.1 test-mode case without PMC pins that release's US-account method order.
`webhook-events.json` pins the 23 historical events; endpoint API versions and
duplicate API-key storage are historical inputs too. Credentials are inert test
strings. The webhook URL is the only environment-dependent substitution.

## Adding coverage

Add a named case to `cases.php`, with only the input overrides it needs. For a new
intentional behavior, add a small helper under `helpers/upgrade-changes/` and call
it explicitly from the relevant cases. Existing migration unit tests continue to
own their detailed eligibility matrices; these cases check the combined upgrade.

Each setting change declares `[ old, new ]`; `null` means an absent key, not a
wildcard. Prerequisites and final values must match, and expected writes must
occur. Two changes cannot claim the same stored key in one stage because only
that stage's final state is observable. Merchant-visible values stay unchanged
unless explicitly declared otherwise; webhook and other maintenance writes are
still checked exactly.

Do not exempt whole settings arrays or endpoints, derive expected values from
production defaults, or bless unexplained preference changes to make a test pass.
Keep negative tests proving that the shared check rejects unwanted changes.

## Limits

This is an in-process integration test of selected lifecycle entry points. It
does not load an old plugin binary, replay every `init`/`admin_init` callback,
dispatch the REST route through authentication, run Apple Pay domain registration
or WooCommerce gateway toggling, render checkout, make payments, or test rollback
and multisite. Fresh-request simulation resets gateway/API/PMC/cache/hook-manager
objects and constructor-added hooks while keeping persisted cache rows and
migration flags. It is not a separate PHP request.

Release-to-release installation, complete admin/checkout requests and rollback
belong in a separate upgrade environment. A PMC eligibility transition with
conflicting local and remote selections also needs an explicit source-of-truth
decision; the eligible fixtures here deliberately have agreeing selections.
