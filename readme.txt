=== WooCommerce Stripe Payment Gateway ===
Contributors: woocommerce, automattic
Tags: credit card, stripe, payments, woocommerce, woo
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 10.8.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Attributions: thorsten-stripe

Accept debit and credit cards in 135+ currencies, many local methods like Alipay, ACH, and SEPA, and express checkout with Apple Pay and Google Pay.

== Description ==

Changing consumer behavior has resulted in an explosion of payment methods and experiences, which are great for increasing conversion and lowering costs—but can be difficult for businesses to maintain. Give customers a best-in-class checkout experience while you remain focused on your core business. This is the official plugin created by Stripe and WooCommerce.

= Drive 11.9% in revenue with an optimized checkout experience from Stripe =

The enhanced checkout experience from Stripe can help customers:

- **Boost conversion:** Provide an optimal experience across mobile, tablet, and desktop with a responsive checkout, and offer 23 payment methods, including [Link](https://stripe.com/payments/link), [Apple Pay](https://woocommerce.com/apple-pay/), and [Google Pay](https://www.google.com/payments/solutions/), out of the box.
- **Expand your customer base:** Convert customers who might otherwise abandon their cart with buy now, pay later methods like Klarna, Affirm, and Afterpay/Clearpay, wallets like Apple Pay, Google Pay, Alipay, and WeChat Pay, and local payment methods such as Bancontact in Europe and Alipay in Asia Pacific. Deliver a localized payment experience with out-of-the-box support for localized error messages, right-to-left languages, and automatic adjustment of input fields based on payment method and country.
- **Meet existing customer demand and localize the experience:** Offer [local payment methods](https://stripe.com/guides/payment-methods-guide), such as ACH Direct Debit, Bacs Direct Debit, Bancontact, BECS Direct Debit, BLIK, Boleto, Cash App Pay, EPS, iDEAL, Multibanco, OXXO, Pre-authorized debit payments, Przelewy 24, and SEPA Direct Debit, and let customers pay in their local currency across 150+ countries with [Adaptive Pricing](https://support.stripe.com/questions/adaptive-pricing).
- **Fight fraud:** Detect and prevent fraud with [Stripe Radar](https://stripe.com/radar), which offers seamlessly integrated, powerful fraud-detection tools that use machine learning to detect and flag potentially fraudulent transactions.
- **Accept in-person payments for products and services:** Use the Stripe Terminal M2 card reader or get started with no additional hardware using Tap to Pay on iPhone, or Tap to Pay on Android.
- **Support subscriptions:** Support recurring payments with various payment methods via [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/).
- **Manage cash flow:** Get paid within minutes with Stripe Instant Payouts, if eligible.
- **Achieve [PCI-DSS](https://docs.stripe.com/security) compliance with [Stripe Elements](https://stripe.com/payments/elements) hosted input fields.**
- Support Strong Customer Authentication (SCA).

Stripe is available for store owners and merchants in [46 countries worldwide](https://stripe.com/global), with more to come.

== Compatibility Notes ==

The following items note specific versions that include important changes, features, or deprecations.

* 10.8.0
   - Optimized Checkout Suite enabled by default for eligible existing stores
   - Adaptive Pricing enabled by default for eligible existing stores
   - Removed deprecated code and constants from 10.2.0 and before - [Full list - PR #5294](https://github.com/woocommerce/woocommerce-gateway-stripe/pull/5294)
* 10.7.0
   - Optimized Checkout Suite re-enabled by default for new installs
   - Adaptive Pricing enabled by default for new installs
   - Removed deprecated backend methods that called wc_deprecated_function - [Full list - PR #5066](https://github.com/woocommerce/woocommerce-gateway-stripe/pull/5066)
* 10.6.0
   - Adaptive Pricing available
* 10.4.0
   - Optimized Checkout Suite no longer enabled by default for new installs
   - Removed the main Payment Request Buttons backend class, WC_Stripe_Payment_Request, which was deprecated in 10.2.0
   - Removed the deprecated WC_Stripe_Apple_Pay class
* 10.3.0
   - Removed legacy checkout payment method classes and settings retrieval methods
* 10.2.0
   - Optimized Checkout Suite enabled by default for all new installations
   - Add minimum transaction amounts for BRL, INR, NZD, THB, CZK, HUF, AED, MYR, PLN, RON
* 10.1.0
   - Improved express checkout address handling for countries without state/postal codes
* 10.0.0
   - Payment Request Buttons are fully replaced by Express Checkout
   - Legacy Checkout is fully deprecated and no longer available
* 9.8.0
  - Optimized Checkout Suite available via a configuration setting
  - We will disable the Affirm or Klarna payment methods if the respective official plugin is enabled
* 9.7.0
  - Improved express checkout support for custom checkout fields
  - Validate customer details against required billing fields from checkout before sending to Stripe
* 9.6.0
  - Legacy checkout deprecated by default
  - Voucher payment methods can be used for subscription purchases when manual renewals are available
  - Include extension data from block checkout for express checkout orders
  - Add hooks to support custom checkout fields for classic checkout
* 9.5.0
   - Synchronize payment methods with Stripe
   - Support Pre-Authorized Debit (PAD) in Canada and the US
   - Support BLIK in Poland and from other EU countries
   - Support BECS Direct Debit payments in Australia

== Frequently Asked Questions ==

= In which specific countries is Stripe available? =

Stripe is available in the following countries, with more to come:

- Australia
- Austria
- Belgium
- Brazil
- Bulgaria
- Canada
- Croatia
- Cyprus
- Czech Republic
- Denmark
- Estonia
- Finland
- France
- Germany
- Gibraltar
- Greece
- Hong Kong
- Hungary
- India
- Ireland
- Italy
- Japan
- Latvia
- Liechtenstein
- Lithuania
- Luxembourg
- Malaysia
- Malta
- Mexico
- Netherlands
- New Zealand
- Norway
- Poland
- Portugal
- Romania
- Singapore
- Slovakia
- Slovenia
- Spain
- Sweden
- Switzerland
- Thailand
- United Arab Emirates
- United Kingdom
- United States

= Does this require an SSL certificate? =

Yes. In Live Mode, an SSL certificate must be installed on your site to use Stripe. In addition to SSL encryption, Stripe provides an extra JavaScript method to secure card data using [Stripe Elements](https://stripe.com/elements).

= Does this support both production mode and sandbox mode for testing? =

Yes, it does. Both production and test (sandbox) modes are driven by the API keys you use with a checkbox in the admin settings to toggle between both.

= Where can I find documentation? =

Refer to the [Stripe WooCommerce Extension documentation for more information, including how to set up and configure the extension](https://woocommerce.com/document/stripe/).

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the [Plugin Forum](https://wordpress.org/support/plugin/woocommerce-gateway-stripe/).

== Screenshots ==

1. With the enhanced checkout from Stripe, you can surface 23 payment methods including buy now, pay later methods; and Link, an accelerated checkout experience.
2. Link autofills your customers’ payment information to create an easy and secure checkout experience.
3. Convert customers who would usually abandon their cart and increase average order value with buy now, pay later options like Klarna, Afterpay, and Affirm. Accept credit and debit card payments from Visa, Mastercard, American Express, Discover, and Diners.
4. Stripe Radar offers seamlessly integrated, powerful fraud-detection tools that use machine learning to detect and flag potentially fraudulent transactions.
5. Accept in-person payments for products and services using the Stripe Terminal M2 card reader.
6. Get started with no additional hardware using Tap to Pay on iPhone, or Tap to Pay on Android.

== Changelog ==

= 10.8.3 - 2026-06-22 =
* Fix - Ensure Adaptive Pricing appears on the checkout page when enabled

= 10.8.2 - 2026-06-15 =
* Fix - Disable Adaptive Pricing when webhooks are disabled
* Fix - Fall back to standard checkout when Adaptive Pricing can't initialize against a conflicting Stripe.js version
* Fix - Correctly distinguish Adaptive Pricing checkout sessions from agentic ones in webhooks so their payments aren't dropped
* Fix - Prevent classic checkout from submitting an empty payment method while the Stripe Payment Element is re-mounting after a checkout update
* Fix - Prevent Adaptive Pricing orders from being stuck in pending after a successful payment
* Fix - Mark Adaptive Pricing orders as failed promptly when a payment is declined, instead of leaving them in Pending Payment

= 10.8.1 - 2026-06-11 =

* Fix - Prevent a fatal error when a Bancontact, iDEAL or Sofort payment is placed with SEPA token saving enabled through the Adaptive Pricing checkout, which left the order unpaid
* Fix - Hide the save payment method checkbox for Bancontact, iDEAL and Sofort in the Adaptive Pricing checkout, where Stripe cannot save them as SEPA Direct Debit tokens
* Fix - Allow card and Link at classic checkout when the order has no billing country, instead of incorrectly blocking the payment as unavailable

= 10.8.0 - 2026-06-08 =

**New Features**

* Add - Default Optimized Checkout Suite and Adaptive Pricing on for all eligible stores, with in-app notices announcing the change
* Add - Allow shoppers to change a subscription payment method using Express Checkout (Apple Pay, Google Pay, Link)
* Add - Add a setting to control whether Express Checkout is shown on the WooCommerce Subscriptions change payment method page
* Add - Register Stripe gateway capabilities with the WordPress Abilities API for agent access (default off; opt in via the `wc_stripe_abilities_enabled` filter)

**Important Fixes and Updates**

* Remove - Remove deprecated code up to version 10.2.0
* Update - Improve express checkout load times by preloading the resources
* Update - Ensure payment method restrictions based on account and shopper countries are up to date
* Fix - Send Klarna's preferred locale in the confirmation token (Optimized Checkout) flow so cross-border customers can complete identity verification
* Fix - Store transaction IDs for orders when we get charges to ensure we can refund correctly
* Fix - Ensure the bank statement descriptor is included when creating payment intents for alternative payment methods
* Fix - Prevent Stripe API calls from failing due to DNS resolution issues
* Fix - Recover a missing Stripe charge ID from the order's payment intent so affected orders stay refundable
* Fix - Add an order note and call action 'wc_stripe_unexpected_charge_detected' when a Stripe charge is captured for an order that was already paid via a different gateway
* Fix - Restore "Refund via Gateway" button and Stripe dashboard transaction link for Amazon Pay orders by keeping Amazon Pay registered in the gateway list on order edit and refund pages
* Fix - Don't auto-cancel Stripe orders that have already been paid, preventing paid orders left at pending (e.g. by a checkout/webhook race) from being cancelled as unpaid
* Fix - Add an order note when a paid-but-pending Stripe order is kept from being auto-cancelled, so stores can review and reconcile the stuck status

**Other Fixes and Updates**

* Remove - Remove giropay from new checkouts (deprecated by Stripe on 2024-06-30); legacy refund and past-order rendering preserved
* Add - Show Apple Pay / Google Pay branding on saved card tokens in My Account → Payment Methods and at checkout
* Add - Detect Stripe API outages (network failures, timeouts, 5xx responses) and surface a wp-admin notice instead of crashing or showing misleading "couldn't connect" messages
* Fix - Use a placeholder billing last name for single-name express checkout payments
* Fix - Prevent Stripe from rendering an unexpected "Address Line 2" field inside the Payment Element
* Fix - Incorrect payment method title on the order confirmation page when Optimized Checkout is enabled
* Fix - Preserve saved card branding when the same card is later used via a wallet, and render multi-word brands (e.g. Cartes Bancaires) correctly
* Fix - Add empty state mapping for Morocco in express checkout
* Fix - Respect the iDEAL/Wero and Bancontact save toggle when Optimized Checkout is enabled
* Fix - Ensure the save-payment-method checkbox is unchecked when a non-reusable payment method is selected in Optimized Checkout
* Add - Add a "Release notes" link to the WooCommerce Stripe row on the Plugins page that opens the changelog modal
* Add - Append a "what's new" changelog link to the Updated! message after manually updating the plugin from the Plugins page
* Add - Implement wc_stripe_logger_can_log filter to allow for targeted logging on busy sites
* Fix - Dismissible subscription detached admin notices
* Fix - Fix admin banner dismissal and display logic
* Fix - Don't default Adaptive Pricing on when first connecting a Stripe account that is ineligible for Adaptive Pricing
* Fix - Make the "Update now" link in the account status tooltip clickable and improve its contrast against the tooltip background
* Fix - Correct Amazon Pay button preview rendering in the Full Site Editor block cart and checkout pages
* Fix - Require a connected account for the target mode before switching between test and live
* Fix - Show better message when express checkout preview is not available
* Fix - Add guards against invalid values for webhook state timestamps
* Fix - Prevent saved Link, Cash App Pay, SEPA, Amazon Pay and Bacs Direct Debit payment methods from being duplicated when viewing My Account → Payment methods
* Fix - Resolve console errors shown when editing the Blocks checkout page
* Fix - Update the order description and metadata on the payment intent after an Adaptive Pricing payment completes
* Dev - Memoize the Express Checkout button visibility check within a request
* Tweak - Drop redundant "Test mode:" label from test payment instructions on Blocks checkout, which already shows a Test Mode badge
* Tweak - Remove the confirmation dialog when disabling a payment method in settings

**Internal Changes and Upcoming Features**

* Fix - Improve handling of skipped products and in-flight syncs in Agentic Commerce sync dashboard
* Dev - Add Jurassic Ninja preview link for PRs to enable one-click browser testing
* Dev - Exclude AGENTS.md and CLAUDE.md contributor-instruction files from the built plugin zip
* Dev - Skip Agentic Commerce catalog upload when content is unchanged
* Dev - Align Agentic Commerce sync status UI with the WordPress design system
* Dev - Refactor docker setup to be worktree friendly
* Dev - Remove redundant `get_retrievable_type()` overrides from payment methods
* Dev - Add Claude Code skills and review rules under .claude/ to capture repo-specific contributor guidance
* Dev - Move some independent classes into autoloader
* Dev - Require explicit visibility for class constants
* Dev - Add payment token classes to autoloader
* Dev - Add Jest tests for ECE payment flow
* Dev - Use explicit method calls for upgrade checks and tools
* Tweak - Reduce JS bundle sizes by using native browser features instead of polyfills
* Tweak - Move UPE-only appearance helpers out of the shared stripe-utils module
* Dev - Add wc_stripe_agentic_commerce_should_sync_product filter so adapters can exclude products from the Agentic Commerce catalog, inventory, and archive syncs
* Dev - Move additional classes to autoloader, including email and migration classes
* Dev - Add e2e tests for subscriptions and Optimized Checkout Suite and support stricter payment confirmation checks

[See changelog for full details across versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
