# Using a Stripe Restricted API Key

The WooCommerce Stripe Payment Gateway can run on a [Stripe Restricted API Key](https://docs.stripe.com/keys#create-restricted-api-secret-key) (`rk_live_` / `rk_test_`) in place of the standard secret key. This document lists the permissions such a key needs and how to install it.

**How the permission list was built:** every row traces to a call site in the plugin source. It has not been confirmed against a Stripe request log, so treat it as a starting point and confirm it against your own store using the method in [Confirm it for your store](#confirm-it-for-your-store). That is the only way to account for the features your store actually uses.

**Scope:** written for a store that has already connected to Stripe. Your publishable key stays a `pk_` key; Stripe has no restricted equivalent, and the plugin requires both keys to consider the account connected.

## Installing a restricted key

There are two supported ways to supply the key:

### Option 1: wp-config.php constants (recommended)

Since version 11.0.0, the plugin reads the secret keys from constants when they are defined. Add to `wp-config.php`, above the `/* That's all, stop editing! */` line:

```php
define( 'WC_STRIPE_SECRET_KEY', 'rk_live_...' );      // live mode
define( 'WC_STRIPE_TEST_SECRET_KEY', 'rk_test_...' ); // test mode
```

A defined constant overrides the key stored in the database on every read, and settings saves never write the constant's value into the database. This is the recommended mechanism because:

- The key never lives in the database, so a database leak does not expose it.
- The override survives the connection refresh that runs on some OAuth connection types, which would otherwise overwrite a key stored in the database.

Note: defining the constants does not remove any key that is already stored in the database from an earlier OAuth connection or manual save. Removing stored keys is a separate hardening step; leave `webhook_secret` / `test_webhook_secret` in place, since they are needed at runtime to verify incoming webhooks.

### Option 2: the account keys REST endpoint

The settings screen has no field for entering keys manually, but the REST endpoint the screen uses accepts them (requires the `manage_woocommerce` capability):

```
POST /wp-json/wc/v3/wc_stripe/account_keys
{ "test_secret_key": "rk_test_...", "test_publishable_key": "pk_test_..." }
```

Keys saved this way are stored in the `woocommerce_stripe_settings` option, like OAuth-issued keys.

## Required permissions

### Tier 1: required for any store

| Resource | Level | Why |
| --- | --- | --- |
| PaymentIntents | Write | Every checkout path, plus capture and void |
| SetupIntents | Write | Saving a card, zero-total subscriptions, adding a payment method |
| Customers | Write | Creating and updating customers, plus the Customers search endpoint used to avoid duplicates |
| PaymentMethods | Write | Attaching, detaching, reading and updating saved payment methods |
| Charges and refunds | Write | One picker row covers both; refunds require Write and refunding is a declared gateway capability. Charge reads run after every payment |
| Balance Transaction Sources | Read | Recording the Stripe fee and net on the order after every payment and refund. This is the row named "Balance Transaction Sources", not "Balance" |
| Account (`GET /v1/account`) | Read | Account country, default currency, capabilities. **The picker row for this endpoint is unconfirmed**; see below |
| Webhook endpoints | Write | The plugin creates, lists, updates and deletes its own webhook endpoint |
| Payment Method Configurations | Write | Reading and writing which payment methods are enabled |
| Sources | Read | Legacy `src_` payment methods only; droppable, see below |

### The account permission is unresolved

The plugin calls `GET /v1/account` (`WC_Stripe_API::retrieve( 'account' )`, from `WC_Stripe_Account`) to read the connected account's `capabilities`, `country`, `default_currency` and statement descriptor settings. Stripe's permission picker has no row named "Account"; the nearest is **Accounts: Read** under the Connect category, which is the most likely row governing it, but that mapping is inferred from resource naming and has not been confirmed.

This permission fails dangerously: a response missing `capabilities` makes payment methods disappear from checkout with no error anywhere. After granting your best guess:

1. Load the Stripe settings screen. Account details rendering means the read worked; "your API keys are no longer valid" means it did not.
2. Load the checkout page and confirm your payment methods still appear.
3. If the request 403s, the response body in Stripe's request log names the exact permission required. That answer is definitive.

The plugin reads no identity data from the account object: no ID numbers, no `individual`, no `company`. It reads `requirements` (which verification obligations are outstanding, not the documents) and only currency codes from `external_accounts`.

### Tier 2: required per feature

Grant these only if you use the feature.

| Feature | Resource | Level | Notes |
| --- | --- | --- | --- |
| Apple Pay, express checkout | Payment Method Domains | Write | Read is not enough; the plugin reads the registration result back off its own write. Registration re-runs on any key change and fails quietly without this |
| Optimized Checkout, Adaptive Pricing | Checkout Sessions | Write | Needs the list endpoint too, not just create and retrieve |
| WooCommerce POS, In-Person Payments | Terminal | Write | Connection tokens and location management |
| Agentic Commerce product feed | Files | Write | Uploads the product feed and reads the error report back |
| Agentic Commerce product feed | Import Sets | Write | Creates and polls the import |
| Abilities / MCP tooling | Balance, Balance transactions, Payouts, Disputes | Read | Only the agent-facing Abilities layer reads these; checkout never does |

### Legacy: Sources

**Sources: Read** covers saved `src_` payment methods and the migration of legacy SEPA subscription tokens. Drop it to None only if you have no saved `src_` tokens and no legacy SEPA subscriptions awaiting migration. If Sources never appears in your key's request log over a full billing cycle, it can be turned off.

### Not needed

A code search finds no call to any of these; leave them at **None**: `/v1/accounts` (plural, Connect platform operations), `oauth/token`, Subscriptions, Invoices, Prices, Products, Coupons, Tax Rates, Credit Notes, Financial Connections, Radar, Reviews, Ephemeral Keys, Issuing, Treasury, Payouts write, dispute evidence write.

Specifics that surprise people:

- **ACH needs nothing extra.** Bank account collection happens entirely in the browser; the server only reads fields off the payment method.
- **Subscriptions need nothing extra.** WooCommerce Subscriptions bills through PaymentIntents. Stripe's own Subscriptions, Invoices and Prices resources are never touched.
- **No individual payment method needs its own permission.** Klarna, Affirm, Afterpay, Amazon Pay, Cash App, WeChat Pay, Alipay, BLIK, P24, iDEAL, Bancontact, Boleto, OXXO and Multibanco are all values on a PaymentIntent.
- **Radar and Payouts, recommended by older community guides, are not needed.** The plugin never calls a `radar/*` endpoint (Radar's decision arrives on the charge), and only the read-only Abilities layer touches payouts.

## Confirm it for your store

1. Create a restricted key in a Stripe sandbox with the permissions above.
2. Install it, then exercise every Stripe-touching flow your store has: load the Stripe settings screen, toggle a payment method, configure webhooks, place an order, refund an order, capture an authorized order, add and remove a saved payment method.
3. Open the key's request log: Stripe Dashboard > API keys > overflow menu next to the key > View request logs.
4. Compare: `GET` is read; `POST` and `DELETE` are write. Anything in the log missing from the tables above is a gap in the tables; anything in the tables that never appears is a permission you can remove.
5. Repeat in live mode with a matching key.

## If something breaks, read the request log

The plugin reports a missing permission badly: `WC_Stripe_API::retrieve()` treats any 401 as an invalid key, and after five consecutive 401s it stops making read requests for two hours and clears the cached account data. A restricted key missing a single read permission therefore looks like a broken key ("the API keys we've saved for you are no longer valid"), not a permission error.

- Diagnose from Stripe's request log, where the 403 response body names the exact permission to add. Do not rely on the plugin's admin notices.
- After fixing the key, re-save the keys (or wait out the block): saving through the account keys endpoint clears the two-hour block immediately.
