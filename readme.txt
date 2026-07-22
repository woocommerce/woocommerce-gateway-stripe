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

* 10.9.0
   - Express checkout processes classic checkout custom fields by default (opt out via the wc_stripe_express_checkout_enable_classic_checkout_custom_fields filter)
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

= 10.9.0 - xxxx-xx-xx =
* Fix - Surface an error and block checkout when the Adaptive Pricing order total can't be synced with Stripe, instead of silently letting the buyer pay a stale amount
* Fix - Stop attaching Level 3 data to captures and payments for non-card methods
* Fix - Add the missing order and customer metadata to Adaptive Pricing payment intents
* Add - Show a "Paid by customer" row with the Adaptive Pricing amount and currency on the order edit page
* Fix - Send the admin New Order and customer Processing emails when an asynchronous payment method (iDEAL, Klarna, Bancontact) is confirmed via the deferred webhook path
* Fix - Use the Amazon Pay custom button size setting on the product, cart, and checkout pages instead of falling back to the Apple Pay/Google Pay size
* Tweak - Render the Express Checkout button on the cart and checkout from page-bootstrapped data, removing a cart-details request from the critical path to first button render
* Fix - Show the Stripe payment block as supported in the Checkout block editor when Optimized Checkout is enabled and only non-card methods (e.g. Cash App Pay) are active
* Tweak - Memoize the Blocks Express Checkout button so it no longer re-renders the Stripe element on unrelated cart updates
* Fix - Keep the subscription payment-method row in sync with the saved-card list when a card has been used both directly and through Apple Pay/Google Pay
* Tweak - Dim the test mode checkbox when it can't be toggled so the disabled state is visible in the UI
* Add - Add dedicated settings page for Link with button size and location customization
* Fix - Ignore incoming webhook events whose Stripe account does not match the connected account to avoid acting on another account's data
* Fix - When changing a subscription's payment method, reflect the new card and its Apple Pay/Google Pay branding on My Account and clarify the admin order note
* Dev - Use a shared hook manager to prevent duplicate subscription hook registrations
* Dev - Initial infrastructure for more complex Agentic feed filtering
* Dev - Agentic Commerce: add the shareable woocommerce_agentic_commerce_disable_checkout filter, deprecating the wc_stripe_-prefixed twin
* Fix - Prevent unnecessary Stripe payment method creation when shortcode checkout has empty required fields
* Fix - Prevent classic and Blocks checkout submissions from failing while the Stripe Payment Element is re-mounting after a checkout update
* Fix - Decommission the previously configured webhook before connecting via OAuth so reconnecting to a different Stripe account no longer leaves an orphaned webhook on the old account
* Fix - Send the billing address to Stripe on the Pay for Order page so payments aren't incorrectly blocked by Stripe Radar rules
* Update - Replace shipping AJAX endpoints with Store API calls for Express Checkout Element
* Tweak - Consolidate the default payment intent metadata fields into a shared method so they stay consistent across payment flows
* Fix - Prevent an uncaught "Stripe initialization data is not available" error on product pages when express checkout loads without the Stripe payment configuration
* Add - Stripe admin pages to the WordPress Command Palette
* Fix - Prevent a possible fatal error when saving the payment method on a subscription order
* Dev - Use clsx library instead of classnames
* Fix - Append the 3-letter currency code to the Stripe Fee and Stripe Payout amounts in WP Admin order totals when the Stripe account currency differs from the order currency
* Dev - Add more robust purchase checks in e2e tests
* Fix - Only create an agentic commerce order on the site that produced the checkout, preventing duplicate or wrong-site orders when multiple stores share one Stripe account
* Dev - Add a Code Comment Conventions section to AGENTS.md to standardize agent and contributor comment guidance
* Fix - Show an error notice when a Stripe OAuth connection attempt fails its security check
* Fix - Run the woocommerce_gateway_description filter for Stripe payment methods so third parties can add or replace their checkout descriptions
* Dev - Deprecate the internal WC_Stripe_Feature_Flags::is_amazon_pay_available() helper now that Amazon Pay is permanently enabled
* Fix - Show correct tax in the Apple Pay / Google Pay product preview for higher quantities, add-ons, and non-taxable products
* Fix - Preserve decimal product quantities for products when adding to the cart through Apple Pay / Google Pay express checkout
* Add - Show a "What's new" inbox note after the plugin updates, including via auto-update
* Tweak - Don't show the Stripe API outage notice on local and development sites, where Stripe is often unreachable for benign reasons
* Fix - Show a loading spinner while an Express Checkout wallet payment (Amazon Pay, Apple Pay, Google Pay) is processed so the page no longer appears frozen
* Tweak - Clarify in the saved payment methods setting that Link by Stripe collects save consent in the payment form instead of a separate checkbox
* Update - Deprecate the wc_stripe_agentic_commerce_should_sync_product filter in favor of the shareable woocommerce_agentic_commerce_should_sync_product
* Add - Per-product "Agentic Commerce" exclude toggle on the product editor's Inventory tab
* Update - Replace the legacy add-to-cart AJAX endpoint with a Store API call for variable products in Express Checkout
* Update - Add booking products to the cart via the Store API in Express Checkout when WooCommerce Bookings supports it
* Dev - Rename internal Blocks API references to Store API in the Express Checkout client for naming consistency
* Fix - Show the card decline error message on classic-shortcode checkout pages instead of silently failing
* Add - Verify that Stripe.js was served from the official Stripe origin before processing checkout payments
* Fix - Exclude WooCommerce Subscriptions products from the Agentic Commerce product feed so they no longer make every sync report a partial success
* Add - Add an Agentic Commerce checkout mode that redirects shoppers to your store to check out instead of completing the purchase in the AI agent
* Tweak - When enabling manual capture, clarify to agentic commerce merchants that Agentic Commerce purchases follow the capture setting in the Stripe agentic commerce dashboard
* Fix - Stop retrying the Stripe App connection refresh when the connection has permanently expired
* Dev - Centralize agentic commerce feed scheduling in integration class
* Fix - Enforce and save custom required checkout fields when paying with express checkout on classic checkout
* Update - Process classic checkout custom fields in express checkout by default (opt out via the wc_stripe_express_checkout_enable_classic_checkout_custom_fields filter)
* Dev - Update subscription E2E fixtures to use WooCommerce Subscriptions product plans
* Fix - Prevent a fatal checkout error when a third-party integration registers callbacks against the Payment Request class removed in 10.4.0
* Tweak - Cap the Agentic Commerce feed preview scan so it stays responsive on large catalogs instead of timing out
* Dev - Make the Express Checkout entrypoint bootstrap test deterministic to remove a CI flake
* Fix - Prevent an error when creating the Adaptive Pricing checkout session for logged-in customers without a saved billing address
* Add - Add a wc_stripe_is_adaptive_pricing_supported filter so extensions can disable Adaptive Pricing for carts their flows are incompatible with
* Fix - Show the selected variation's line items in the Apple Pay/Google Pay payment sheet on variable product pages, instead of the previously selected variation's breakdown
* Tweak - Break down the Agentic Commerce feed preview's excluded product count by reason so subscriptions are no longer mistaken for a merchant-configured filter
* Fix - Render the Express Checkout settings button preview background based on the button color
* Update - Update the Stripe API version and the Stripe.js release train to 2026-03-25 (dahlia)
* Fix - Fall back to the standard Stripe payment form when another plugin loads an older, incompatible version of Stripe.js, so Adaptive Pricing and Optimized Checkout keep working
* Update - Move the checkout sessions availability check to WC_Stripe_Helper and remove the deprecated wc_stripe_is_checkout_sessions_available filter
* Fix - Prevent Adaptive Pricing payments from failing when the checkout return URL is relative
* Add - Add a wc_stripe_subscription_renewal_blocked_by_radar action hook that fires when Stripe Radar blocks a subscription renewal payment
* Fix - Include the statement descriptor when creating payment intents for ACSS Debit and BLIK payments
* Fix - Represent negative fees and taxes as discounts in Level 3 data so manually capturing an order edited to include a negative fee no longer fails
* Fix - Ensure all WordPress hooks have filter documentation
* Fix - Refund the shopper when a Stripe payment is captured after the order was cancelled, instead of leaving them charged for a cancelled order
* Fix - Restrict payment intent creation to payment methods that do not support deferred intent
* Fix - Clear the Adaptive Pricing order total error notice once a later update succeeds
* Fix - Accept express checkout addresses whose province is entered with a curly apostrophe, so Apple Pay no longer rejects them
* Update - Show a clear message when manual capture is blocking Adaptive Pricing from being enabled
* Fix - Lock the order while confirming a 3DS card payment so a concurrent webhook can't complete it twice, duplicating stock reduction, order notes, and emails
* Fix - Store the Stripe refund ID on each WooCommerce refund record so orders with multiple partial refunds retain every refund ID
* Update - Document that WooCommerce coupons and their usage limits do not apply to agentic checkout, and reject discounted agentic sessions with an explicit error instead of an opaque total mismatch

[See changelog for full details across versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
