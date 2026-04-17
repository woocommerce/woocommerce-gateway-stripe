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
* Remove - Remove EU adaptive pricing disclosure component from classic and Blocks checkout as it is shown natively within the Stripe currency selector element
* Dev - Add paratest for parallel PHP unit test execution
* Fix - Accept regional language names for Spanish provinces (e.g., Basque "Gipuzkoa") in Apple Pay and express checkout address validation
* Fix - Restore missing saved payment tokens when Optimized Checkout Suite is enabled
* Add - Add exit survey to capture merchant feedback on plugin deactivation and gateway disablement
* Update - Defer checkout sessions webhook processing via Action Scheduler to prevent race conditions when webhook events arrive before order metadata is stored
* Fix - Hide duplicate store-level save checkbox when Stripe Link is enabled on checkout
* Dev - Autoload all Agentic Commerce classes via Composer classmap, removing manual require_once calls
* Update - Show "Payment Options" as the Optimized Checkout title on classic checkout and "Payment Methods" on Blocks checkout instead of "Stripe"
* Dev - Separate Agentic Commerce merchant-controlled is_enabled setting from the developer feature flag
* Fix - Move test mode instructions above the Adaptive Pricing currency selector in classic checkout
* Fix - Render the Adaptive Pricing currency selector immediately above the payment element in classic checkout
* Update - Show Express Checkout on block checkout when Adaptive Pricing is enabled
* Fix - Fix checkout session creation for guest users
* Add - Allow payment methods for other currencies to be enabled when Adaptive Pricing is enabled
* Fix - Re-compute Stripe PE appearance after web fonts load to prevent fallback font rendering
* Fix - Better background color detection for block themes and allow fonts from fonts.bunny.net
* Update - Shorten test mode messaging, add Test Mode badge on Blocks checkout, and add copy-to-clipboard for test card numbers
* Fix - Prevent brief display of wrong title on classic checkout when Optimized Checkout is enabled
* Fix - Update Stripe Fee and Stripe Payout values correctly after partial capture by replacing authorization-phase values instead of adding to them
* Fix - Add defensive checks before running renewal meta cleanup when renewal/subscription objects are missing or invalid
* Dev - Add metadata accessor methods for subscription objects to WC_Stripe_Order_Helper, centralizing subscription-specific metadata handling
* Add - Include specific information on converted currency for adaptive pricing in order confirmation emails
* Fix - Use the order currency instead of the global store currency when creating a payment intent, resolving incorrect charges in multicurrency setups
* Dev - Rename and move the new Checkout Sessions ajax handler class to be autoloaded
* Add - Process payment with adaptive pricing in the classic checkout
* Dev - Add WC_Stripe_Country_Code constants class and replace hardcoded country code strings
* Dev - Update WC_Stripe_Currency_Code constants class with zero-decimal and three-decimal currency lists and replace legacy no_decimal_currencies() usage
* Fix - Resolve intermittent "Missing required customer field: address->line1" error during checkout with auto-account creation
* Update - Add deprecation notices to methods and properties that were deprecated without them in older versions
* Add - New promotional banner to highlight the Stripe Tax extension for OCS-enabled merchants
* Add - Include specific information on converted currency for adaptive pricing in the order received page and order details page
* Add - Support express checkout for free trial subscription products that require shipping
* Fix - Normalize express checkout button spacing on the block cart page in Safari
* Fix - Re-block UI during express checkout post-modal processing so shoppers see a loading state while the checkout API call completes
* Dev - Add product deletion tracking to Agentic Commerce inventory sync: product deletes and trash events are batched and uploaded to Stripe as a product_catalog_feed with delete:true
* Dev - Rename PHPUnit test files and directories to match the WordPress kebab-case naming convention used in includes/
* Add - Process payment with adaptive pricing in the blocks checkout
* Update - Express Checkout button logging will only occur when verbose debug mode is enabled
* Update - Disable the Optimized Checkout Suite in the "Add Payment Method" and "Change Subscription Payment Method" screens
* Dev - Remove unused frontend code: legacy blocks payment request API helpers, related normalize utilities, and unused Stripe icon component
* Add - Allow additional font domains to be included in Stripe fonts
* Dev - Add incremental inventory sync for Agentic Commerce: tracks stock changes via WooCommerce hooks and uploads a minimal inventory_feed CSV to Stripe one minute after the first change
* Dev - Skip registering Stripe email classes when WooCommerce email class is not loaded
* Fix - Add order and payment method validation to prevent errors
* Dev - Remove @woocommerce/currency dev dependency to resolve locutus CVE-2026-32304 (GHSA-vh9h-29pq-r5m8)
* Tweak - Hide pay and cancel actions for pending orders processed via Checkout Session in order received page and My Account orders list
* Fix - Improve default layout when Optimized Checkout is disabled
* Fix - Ensure that we enqueue all needed scripts on payment pages
* Fix - Use floating labels and correct field spacing on Blocks checkout to match WooPayments
* Fix - Improve performance of CSS style lookups
* Fix - Wrap express checkout add-to-cart in try/catch to prevent errors
* Fix - Treat customer-initiated Klarna (and other redirect BNPL) cancellations as recoverable so the order stays retryable and shoppers can complete checkout with another payment method
* Add - Initial implementation of always-expanded Optimized Checkout Suite in shortcode checkout
* Dev - Collapse PHPUnit tests using data providers to reduce duplication and improve test isolation
* Fix - Fix UPE style transition keys for font smoothing properties
* Add - Handle Checkout Session failure webhook events for expired and async failed payments
* Add - Process Checkout Session async payment success webhooks
* Dev - Hide Stripe's testing assistant on checkout page
* Dev - Treat misaligned statements as errors in PHPCS ruleset
* Fix - Put subscription on hold when Stripe Radar blocks a renewal payment to prevent WC Subscriptions from scheduling further retry attempts
* Fix - Fall back to split Stripe payment methods when Optimized Checkout is enabled but Stripe is not the first available gateway
* Dev - Remove checkout sessions feature flag and make the feature available by default
* Fix - Prevent TypeError when processing deferred webhooks using Action Scheduler
* Update - Hide Adaptive Pricing option for Stripe accounts based in India and European Economic Area countries
* Fix - Prevent JavaScript error in `elements.update` when using checkout sessions with adaptive pricing
* Fix - Keep adaptive pricing amount in sync on classic checkout after order total changes
* Fix - Keep adaptive pricing amount in sync on block checkout after order total changes
* Fix - Use a single Checkout Session line item priced at the full payable cart total so adaptive pricing sessions match checkout totals
* Add - Add Ajax endpoint to update line items in a checkout session
* Tweak - Hide the Adaptive Pricing currency selector from classic checkout when a saved payment method is selected
* Add - Allow customers to save payment methods during checkout with adaptive pricing
* Fix - Only collect and send payer phone in Checkout Sessions when the WooCommerce phone field is required
* Fix - Restrict Checkout Session saved payment method options to logged-in customers so guest checkout session creation succeeds
* Add - Add an admin notice and one-click action to move Stripe payment methods to the top of WooCommerce payment gateway order for Optimized Checkout
* Update - Allow Adaptive Pricing for merchant accounts based in EEA countries                                                                                                                               
* Add - Show ECB interbank rate conversion fee notice to EEA-based shoppers on the order received page and in customer order confirmation emails
* Fix - Confirm checkout session with user data in classic checkout for guest user
* Add - Handle redirect payment flow in classic checkout for Checkout Sessions
* Dev - Add automatic changelog entry suggestions to bin/changelog.js
* Fix: Improve UX for the "Stripe first method" notice for Optimized Checkout
* Fix - Change Checkout Sessions (Adaptive Pricing) redirect-based flow to match the existing PaymentIntent flow (redirect to checkout page)
* Fix - Ensure currency selector appears after saved payment methods in classic checkout

[See changelog for full details across versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-stripe/trunk/changelog.txt).
