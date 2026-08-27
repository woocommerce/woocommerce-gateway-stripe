=== WooCommerce Stripe Payment Gateway ===
Contributors: woocommerce, automattic
Tags: credit card, stripe, payments, woocommerce, woo
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 10.8.5
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

* 11.0.0
   - Some express checkout helpers now require the caller to specify whether they are in the WooCommerce Cart context
   - Express checkout merges the wc_stripe_express_checkout_normalize_address filter result over the address it sent; removing a field no longer clears it, return an empty string instead
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

= 11.0.0 - xxxx-xx-xx =
* Add - Show a placement simulator on each Customize express checkouts tab that previews where the express checkout button would and wouldn't appear, with the reason
* Tweak - Dim the button size hint on the Amazon Pay and Link customize tabs so it matches the Apple Pay/Google Pay tab
* Add - Show the fuller sync-eligibility verdict in the Products list Agentic Commerce column and link the excluded-products view from the settings page
* Dev - Move hook registration out of the webhook, order, payment token, Apple Pay registration, and Connect handler constructors so repeated instantiation cannot duplicate callbacks
* Add - Bulk edit, quick edit, and a sync-status column and filter on the Products list for excluding products from the Agentic Commerce catalog sync
* Add - Use Stripe Dynamic Payment Methods for on-session Optimized Checkout payments so eligible methods follow your Stripe Payment Method Configuration
* Dev - Extract Optimized Checkout into a dedicated payment gateway class so the classic UPE and Optimized Checkout flows are handled independently
* Fix - Hide country-restricted payment methods (e.g. iDEAL) from Optimized Checkout when the billing country can't use them
* Fix - Register Multibanco, OXXO, Boleto, Link, and Cash App Pay hooks only once per request so email instructions and thank-you page content are not duplicated
* Fix - Resync the Agentic Commerce catalog when switching between test and live mode, and scope the sync status shown in settings to the active mode
* Dev - Register Stripe.js through a single shared helper
* Fix - Don't show currency admin notices for payment methods when Adaptive Pricing is active
* Tweak - Use database cache for webhook status tracking
* Dev - Add WC_Stripe::get_settings()/update_settings() as the canonical accessors for the main Stripe settings, with the WC_Stripe_Helper shims delegating to them
* Fix - Render classic-checkout card fields when a host optimizer defers render-blocking JavaScript (e.g. SiteGround Speed Optimizer)
* Fix - Leave the Expires column blank instead of showing "N/A" on My Account → Payment methods for saved payment methods that have no expiry date, such as Link, SEPA and Cash App Pay
* Fix - Allow express checkout payments when automatic account password generation is disabled
* Dev - Remove the deprecated @woocommerce/settings npm package; settings are still read from the wc-settings script WooCommerce provides at runtime
* Fix - Exclude password-protected products and products hidden from the catalog from the Agentic Commerce feed
* Fix - Name password protection and hidden visibility as their own reasons in the Agentic Commerce feed preview
* Dev - Make GITHUB_TOKEN optional for the local E2E Docker setup by falling back to the GitHub CLI and then to plugin zips placed in tests/e2e/deps
* Fix - Reload the plugin's payment method settings when refreshing account details, so the settings screen no longer keeps showing stale values
* Update - Fetch express checkout AJAX nonces on demand instead of embedding them in every page
* Fix - Keep the wallet billing and shipping addresses on express checkout orders when the address normalization request returns an unusable response
* Fix - Render the Adaptive Pricing payment element in the store's Stripe locale
* Fix - Stop sending Level 3 data when paying with a non-card payment method through express checkout (e.g. Amazon Pay), which Stripe rejects and can disable Level 3 data for card payments
* Fix - Record the charge-captured state for asynchronously confirmed payments (e.g. ACH) so refunds from wp-admin and the Stripe Dashboard behave correctly
* Fix - Reject negative refund amounts with an explicit error instead of silently refunding the absolute value
* Fix - Hide the Apple Pay and Google Pay express buttons on pages unchecked in their own locations setting, instead of following other wallets' locations
* Fix - Hide the save payment method checkbox for Bancontact, iDEAL and Sofort in the Adaptive Pricing checkout on non-EUR stores whose currency excludes them
* Fix - Stop flagging the checkout page as the cart page (is_cart() returning true) when express checkout buttons are enabled, which made analytics and other plugins misread the page
* Fix - Remove the empty box shown under "Use a new payment method" on classic checkout when the save-payment-method checkbox is hidden

[See changelog for full details across versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
