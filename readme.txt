=== WooCommerce Stripe Payment Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, payments, woocommerce, woo
Requires at least: 6.6
Tested up to: 6.8.3
Requires PHP: 7.4
Stable tag: 10.1.0
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

= 10.2.0 - xxxx-xx-xx =

**New Features**

* Update - Enable the Optimized Checkout Suite feature for all new installations
* Update - Add minimum transaction amounts for BRL, INR, NZD, THB, CZK, HUF, AED, MYR, PLN, RON

**Important Fixes and Updates**

* Add - Implement cache prefetch for account data
* Add - Allow cache prefetch window to be adjusted via the wc_stripe_database_cache_prefetch_window filter
* Add - Add wc_stripe_express_checkout_normalize_address filter for express checkout address normalization
* Update - Include customer data in wc_stripe_create_customer_required_fields filter
* Fix - Ensure state and postal code are optional in express checkout for Gulf countries (UAE, Bahrain, Kuwait, Oman, Qatar)
* Fix - Ensure correct express checkout prices in block cart and checkout with non-default decimal configuration
* Fix - Don't allow WP-Cron jobs to detach payment methods on staging sites
* Fix - Generate OAuth URLs on-demand when connecting to Stripe instead of pre-generating them on page load
* Fix - Always use the current payment method configuration in Optimized Checkout
* Fix - Generate OAuth URLs on-demand when connecting to Stripe instead of pre-generating them on page load

**Other Fixes**

* Update - Changes the list of payment methods shown in the Stripe account connection modal
* Update - Better notices and interactions for disabled express checkout methods
* Update - Changes labels related to saved payment methods from "cards" to "payment methods"
* Fix - Allow payment methods to be disabled when they are not available
* Fix - Ensure Amazon Pay, Apple Pay, and Google Pay display settings are managed correctly
* Fix - Ensure express payment methods are processed correctly when Optimized Checkout is enabled
* Fix - Fix error handling when processing subscription renewals
* Fix - Prefill customer billing information on the Pay for Order and Change Payment Method pages
* Fix - Respect button.radius value of 0 in Express Checkout Element appearance settings
* Fix - Fix revoked secret_key error during the OAuth account connection flow
* Fix - Exclude order parameter from customer creation request arguments

**Internal Changes and Upcoming Features**

* Update - Improves the error log for SSL connection missing when trying to render the express checkout buttons
* Update - Expand Amazon Pay support for all permitted currencies and countries
* Fix - Make token detachment checks use shared logic for detaching payment methods
* Fix - Disable express checkout when Amazon Pay is disabled and the only method
* Fix - Use the built-in Database Cache for the Connect flow data
* Tweak - Hide Amazon Pay from the standard payments in Optimized Checkout
* Dev - Refactor display logic for payment method issue pills
* Dev - Deprecates all the legacy checkout payment method classes
* Dev - Deprecates all the LPM class constants
* Dev - Remove all references to the UPE-enabled feature flag
* Dev - Removing all usages of the `is_stripe_ece_enabled` feature flag method
* Dev - Expands the Stripe Order Helper class to handle mandate ID, Multibanco data, refund status, card brand, charge captured flag, status final flag, and the refund failure reason
* Dev - Remove the merchant email address from the System Status Report
* Dev - Replace the constant reference for the legacy SEPA payment method
* Dev - Add logging with DNS resolution diagnostics for URL validation issues when calling Stripe API
* Dev - Removes the `_wcstripe_feature_upe` feature flag and the related method from the `WC_Stripe_Feature_Flags` class
* Dev - Fixes some incorrect subscriptions support implementations for payment methods
* Dev - Add additional context data to the OAuth connect flow verbose debug logging mode

[See changelog for full details across versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
