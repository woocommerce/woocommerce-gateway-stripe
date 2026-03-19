=== WooCommerce Stripe Payment Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, payments, woocommerce, woo
Requires at least: 6.7
Tested up to: 6.9.1
Requires PHP: 7.4
Stable tag: 10.5.3
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

= 10.6.0 - xxxx-xx-xx =
* Add - Include specific information on converted currency for adaptive pricing in the order received page and order details page
* Add - Support express checkout for free trial subscription products that require shipping
* Fix - Re-block UI during express checkout post-modal processing so shoppers see a loading state while the checkout API call completes
* Dev - Rename PHPUnit test files and directories to match the WordPress kebab-case naming convention used in includes/
* Add - Process payment with adaptive pricing in the blocks checkout
* Update - Express Checkout button logging will only occur when verbose debug mode is enabled
* Update - Disable the Optimized Checkout Suite in the "Add Payment Method" and "Change Subscription Payment Method" screens
* Dev - Remove unused frontend code: legacy blocks payment request API helpers, related normalize utilities, and unused Stripe icon component
* Add - Allow additional font domains to be included in Stripe fonts
* Fix - Add order and payment method validation to prevent errors
* Tweak - Hide pay and cancel actions for pending orders processed via Checkout Session in order received page and My Account orders list
* Fix - Improve default layout when Optimized Checkout is disabled
* Fix - Improve performance of CSS style lookups
* Fix - Prevent incorrect re-enablement of express checkout methods during upgrades

= 10.5.3 - 2026-03-19 =
* Fix - Restore default layout when Optimized Checkout is disabled
* Fix - Prevent incorrect re-enablement of express checkout methods during upgrades

= 10.5.2 - 2026-03-13 =
* Fix - Ensure that we enqueue all needed scripts on payment pages

= 10.5.1 - 2026-03-11 =
**Important Fixes and Updates**
* Fix - Reinstate custom appearance logic
* Fix - Refactor some Amazon Pay helpers to prevent an infinite loop
* Fix - Clear customer cache after saving a new payment method so the Stripe payment method list has correct data

= 10.5.0 - 2026-03-09 =

**Important Fixes and Updates**
* Update - Update Stripe API to 2025-09-30.clover and dynamic Javascript to Clover
* Fix - Allow subscription edits when mandates are present, except for mandates from India
* Fix - Prevent fatal error when order ID in webhook references a refund
* Fix - Hide all payment methods on Add Payment Method page when saved payment methods are disabled
* Fix - Only load Stripe JS (not the full UPE bundle) on product and cart pages when express checkout is disabled
* Fix - Fix script dependencies and script cache versioning for classic checkout
* Update - Remove the deferred intent flag (`wc-stripe-is-deferred-intent`) and the non-deferred intent code path
* Update - Deprecate unused non-deferred intent methods in WC_Stripe_UPE_Payment_Gateway
* Update - Use iDEAL | Wero co-branding to comply with branding changes

**Other Fixes and Updates**
* Dev - Upgrade @stripe/react-stripe-js to ^5.4.1 and @stripe/stripe-js to ^8.6.0 in JavaScript dependencies
* Dev - Remove unused frontend code related to block checkout
* Dev - Remove unused frontend code related to UPE
* Update - Move class instantiations from their definition files to the plugin initialization code
* Fix - Update deprecated WooCommerce block checkout hook from `onCheckoutAfterProcessingWithSuccess` to `onCheckoutSuccess` in the saved token handler
* Fix - Fix currency formatting issue with ISK for express checkouts
* Fix - Remove transient style caching in favor of a simple in-memory frontend cache
* Fix - Improve Stripe element appearance on non-checkout pages
* Fix - Remove WooCommerce session creation on product page load to improve cacheability
* Tweak - Update PHPDoc in admin REST controllers and related code
* Tweak - Improve PHPDoc for migration and notes; minor notes refactor
* Tweak - Update PHPDoc for express checkout classes, block support class, and intent controller
* Tweak - Update PHPDoc for UPE payment method classes
* Tweak - Refactor order helper class to use constants for lock meta keys and reduce code duplication in meta getters
* Tweak - Update PHPDoc and fix minor issues for subscriptions and pre-order compatibility
* Tweak - Add missing JSDoc comments to client-side utility, API, and payment-flow functions

**Internal Changes and Upcoming Features**
* Dev - Remove unit tests that no longer make sense after recent file removals
* Dev - Deprecate old unused feature flag backend methods and remove related code from the frontend
* Dev - Fix WC beta version resolution in tests
* Dev - Fix becs e2e tests
* Dev - Upgrade @types/react to ^18.3.7 in JavaScript dependencies
* Dev - Add CodeRabbit configuration with Stripe-focused review guidance
* Dev - Expand AI agent guidance with directory-level AGENTS and CLAUDE context files
* Dev - Update dependencies for building translations
* Dev - Extract shared banner styled components and dismissNotice utility to eliminate frontend duplication
* Dev - Use PaymentGatewayFeature constants instead of raw strings for payment gateway feature declarations in UPE payment method classes and traits
* Add - Display adaptive pricing currency selector on blocks checkout page
* Add - Display adaptive pricing currency selector on classic checkout page
* Add - Add method to check if adaptive pricing is supported and pass the flag to checkout page
* Add - Add the base CSV feed for agentic commerce
* Add - Backend support for processing payments with Checkout Session when using Adaptive Pricing
* Update - Reconfigure webhooks to include Checkout Session events when Adaptive Pricing is enabled via settings
* Update - Include additional customer information when creating checkout sessions
* Update - Add missing metadata to checkout session objects when processing webhook events

[See changelog for full details across versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
