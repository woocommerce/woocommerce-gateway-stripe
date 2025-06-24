=== WooCommerce Stripe Payment Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, stripe, payments, woocommerce, woo
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 9.5.3
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

= 9.5.3 - 2025-06-23 =

* Fix - Reimplement mapping of Express Checkout state values to align with WooCommerce's expected state formats
* Fix - Adds an exception to be thrown when the order item quantity is zero, during the retrieval of level 3 data from an order
* Fix - Ensure that we migrate payment_request_button_size=medium on upgrade
* Fix - Show correct price in Express Checkout for zero decimal currencies
* Fix - Fixes a possible fatal error with Multibanco purchases when generating the email instructions
* Fix - Fix buggy unsaved changes warning in settings page
* Fix - Use the platform's payment method configuration id constant when rendering the Optimized Checkout
* Update - Improve checks in voucher purchase flow
* Tweak - Track charge completed via webhooks in order notes

= 9.5.2 - 2025-05-22 =

* Add - Implement custom database cache for persistent caching with in-memory optimization
* Update - Remove feature that flags 401s and proactively blocks subsequent API calls until the store has reauthenticated
* Fix - Disable payment settings sync when we receive unsupported payment method configurations
* Fix - Ensure that we use current Stripe API keys after settings updates
* Fix - Fix initial enabled payment methods migration to the Stripe Payment Methods Configuration API

= 9.5.1 - 2025-05-17 =

* Fix - Add a fetch cooldown to the payment method configuration retrieval endpoint to prevent excessive requests
* Fix - Prevent further Stripe API calls if API keys are invalid (401 response)
* Fix - Stop checking for detached subscriptions for admin users, as it was slowing down wp-admin
* Fix - Fix fatal error when checking for a payment method availability using a specific order ID

= 9.5.0 - 2025-05-13 =

**New Features**

* Synchronize payment methods with the Stripe dashboard - if you've connected to Stripe, changes in payment methods are synchronized between the plugin and your Stripe dashboard. Changes from the Stripe dashboard may take a few minutes to flow through to shoppers.
* Support Pre-authorized Debit (PAD) payments in Canada and the US
* Support BLIK payments in Poland and from other EU countries
* Support BECS Direct Debit payments in Australia

**Important Fixes and Updates**

* Update - Add express checkout support for One Page Checkout and other dynamic cart update scenarios
* Fix - Show error notice when 'Add payment method' fails on My Account page in block-based themes
* Add - Add WordPress Action for processing payments with delayed charge attempts due to pre-debit notification period
* Fix - Add caching for the Stripe Payment Method Configuration API
* Fix - Prevent deletion of webhooks for other tools
* Update - Add support for customer order notes and express checkout
* Add - New filter to allow merchants to bypass the default visibility of the express payment method buttons when taxes are based on customer's billing address (`wc_stripe_should_hide_express_checkout_button_based_on_tax_setup`)
* Fix - Improves the subscriptions detached admin notice, making it less intrusive and limiting the querying to 5 subscriptions (avoiding slow loading times)
* Fix - Fixes an issue where the order signature retrieval method could throw a fatal error when the received order parameter is actually an OrderRefund object (instead of a `WC_Order`)
* Fix - Fixes a possible fatal error when a product added to the cart cannot be found (with Payment Request Buttons)
* Fix - Fixed subscription features not being properly registered when hooks were already attached
* Add - Expand Klarna support to some additional countries in EEA
* Update - Hide express checkout buttons when no product variation is selected
* Fix - Express checkout error when using extensions that reduce total cart amount (e.g. Gift Cards)
* Fix - Checkout page focus loss
* Fix - Updated payment method radio button selector to correctly find the selected payment method in different themes
* Fix - Add `wc_stripe_generate_create_intent_request` filter to support mandate information in setup intent creation
* Fix - Prepare mandate data from subscription object on change payment method page

**Other Fixes**

* Fix - Checks for the existence of the `WC_Stripe_Feature_Flags` class before including it during extension initialization
* Fix - Prevents fatal errors for cases where we fail to load product details
* Fix - Address an edge case with webhook URL comparisons
* Add - Only show payment methods in Stripe settings that are available for the connected Stripe account
* Fix - Show correct gateway name in non payments settings pages
* Fix - Fixes the Stripe checkout container visuals when Smart Checkout is disabled
* Fix - Prevent reuse of payment intents when order total doesn't match intent amount
* Fix - Fix invalid IP address error encountered during mandate data creation
* Fix - Compatibility with email preview in the Auth Requested email
* Update - Update Alipay and bank debit icons
* Tweak - Update payment method type check for `charge.succeeded` webhook
* Add - Disable unsupported payment methods in Stripe settings
* Update - Update handling of Puerto Rico as a country in the terminal locations endpoint
* Fix - Fix express checkout button width in shortcode cart page
* Fix - Translation warning when initializing the status page information
* Update - Remove unused express checkout button tracking
* Tweak - Add save payment method parameter to update intent call for non-deferred intent payment methods
* Update - Back button on the settings pages
* Update - Use individual product tax status instead of storewide tax setup when determining express checkout availability
* Dev - Add tracking events when enabling/disabling payment methods.

**Internal Changes and Upcoming Features**

* Feature - Work to support Optimized Checkout
* Feature - Work to support Amazon Pay
* Dev - Splits the code coverage GitHub Actions Workflow into two separate actions
* Dev - Updates the Code Sniffer package to version 1.0.0.
* Dev - Minor fix to e2e setup code
* Dev - Make PHP error log from Docker container available in docker/logs/php/error.log
* Dev - Do not generate filenames with underscores
* Dev - Replaces references to order status values with their respective constants from the WooCommerce plugin.
* Dev - Introduce new payment method constants for the express methods: Google Pay, Apple Pay, Link, and Amazon Pay (backend version)
* Dev - Improves how we handle express payment method titles by introducing new constants and methods to replace duplicate code.

[See changelog for full details across versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
