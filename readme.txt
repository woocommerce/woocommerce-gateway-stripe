=== WooCommerce Stripe Payment Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, payments, woocommerce, woo
Requires at least: 6.7
Tested up to: 6.9.4
Requires PHP: 7.4
Stable tag: 10.6.1
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
- **Meet existing customer demand and localize the experience:** Offer [local payment methods](https://stripe.com/guides/payment-methods-guide), such as ACH Direct Debit, Bacs Direct Debit, Bancontact, BECS Direct Debit, BLIK, Boleto, Cash App Pay, EPS, iDEAL, Multibanco, OXXO, Pre-authorized debit payments, Przelewy 24, and SEPA Direct Debit.
- **Fight fraud:** Detect and prevent fraud with [Stripe Radar](https://stripe.com/radar), which offers seamlessly integrated, powerful fraud-detection tools that use machine learning to detect and flag potentially fraudulent transactions.
- **Accept in-person payments for products and services:** Use the Stripe Terminal M2 card reader or get started with no additional hardware using Tap to Pay on iPhone, or Tap to Pay on Android.
- **Support subscriptions:** Support recurring payments with various payment methods via [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/).
- **Manage cash flow:** Get paid within minutes with Stripe Instant Payouts, if eligible.
- **Achieve [PCI-DSS](https://docs.stripe.com/security) compliance with [Stripe Elements](https://stripe.com/payments/elements) hosted input fields.**
- Support Strong Customer Authentication (SCA).

Stripe is available for store owners and merchants in [46 countries worldwide](https://stripe.com/global), with more to come.

== Compatibility Notes ==

The following items note specific versions that include important changes, features, or deprecations.

* 10.7.0
   - Optimized Checkout Suite re-enabled by default for new installs
   - Adaptive Pricing enabled by default for new installs
   - Removed deprecated backend methods that called wc_deprecated_function - [PR #5066](https://github.com/woocommerce/woocommerce-gateway-stripe/pull/5066)
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

= 10.7.0 - 2026-05-12 =

**New Features**
* Update - Enable Optimized Checkout Suite by default for new installs
* Update - Enable Adaptive Pricing by default for new installs
* Add - Filter wc_stripe_optimized_checkout_title to override the Optimized Checkout payment method title at checkout

**Important Fixes and Updates**
* Update - Remove deprecated backend methods that called wc_deprecated_function
* Add - Display a bank authorization notice for Pre-Authorized Debit (ACSS) payments on checkout
* Fix - Fix compatibility with WooCommerce 10.8 checkout-evidence guard (required created-via in orders)
* Fix - Store Stripe Terminal IPP channel metadata on orders so WooCommerce can identify POS payments and suppress standard transactional emails
* Fix - Cancel pending WC Subscriptions retry when Stripe Radar blocks a renewal payment so further automatic retries do not re-trigger the same Radar block
* Fix - Stale saved-card metadata after updating a card's expiry or CVC
* Fix - Prevent fatal error in wp-admin from overly narrow argument type
* Update - Improve the behaviour for the Optimized Checkout Suite and Adaptive Pricing settings
* Fix - Guard against multiple WC_Stripe instances before plugin is fully initialized

**Other Fixes and Updates**
* Fix - Hide the "move Stripe to the top" Optimized Checkout notice when all payment methods above Stripe are disabled
* Add - Allow Stripe developer widget to be enabled in test mode via wc_stripe_show_stripe_developer_widget filter
* Add - Show Stripe's account sync status in the Account details card

**Internal Changes and Upcoming Features**
* Add - Add Agentic Commerce settings UI with feature introduction, onboarding guide, enable/disable toggle, and webhook secret management
* Add - Add Agentic Commerce admin dashboard for monitoring product feed sync status, history, errors, and triggering manual syncs
* Fix - Use SKU as the Agentic Commerce catalog identifier so checkout.session.completed line items resolve correctly; keep the product-ID fallback for SKU-less products and catalogs synced under the legacy contract
* Fix - Stop Agentic Commerce checkout.session.completed orders from failing on null WC sessions or shipping rates that don't match any configured WC method
* Fix - Detect Agentic Commerce sessions via payment_intent.agent_details so their checkout.session.completed webhooks aren't skipped
* Fix - Surface PHP Throwables from the Agentic Commerce checkout.session.completed flow so fatals are logged, the order rollback runs, and Action Scheduler marks the job failed
* Fix - Look up products by SKU in Agentic Commerce manual approval and tax calculation flows
* Dev - Rename payment request references to express checkout
* Dev - Reduce PR PHP test matrix from 30 to 12 jobs (PHP 7.4, 8.2, 8.5; WC/WP at L and L-2) for faster CI feedback
* Dev - Bump transitive minimatch dev dependency to resolve ReDoS CVE-2026-27903 (GHSA-7r86-cg39-jmmj)

[See changelog for full details across versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
