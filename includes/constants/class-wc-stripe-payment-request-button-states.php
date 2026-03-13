<?php
/**
 * Class WC_Stripe_Payment_Request_Button_States
 *
 * Provides a map between WC states and Payment Request API states.
 * The list is based on libaddressinput: https://github.com/google/libaddressinput,
 * which is used by Chromium based browsers in the native Payment Request address dialog.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once WC_STRIPE_PLUGIN_PATH . '/includes/constants/class-wc-stripe-express-checkout-button-states.php';

/**
 * Known issues/inconsistencies:
 *    1. WC provides a dropdown list of states, but there's no state field in Chrome for the following countries:
 *        AO (Angola), BD (Bangladesh), BG (Bulgaria), BJ (Benin), BO (Bolivia), DO (Dominican Republic),
 *        DZ (Algeria), GH (Ghana), GT (Guatemala), HU (Hungary), KE (Kenya), LA (Laos),
 *        LR (Liberia), LT (Lithuania), MD (Moldova), NA (Namibia), NP (Nepal), PK (Pakistan),
 *        PY (Paraguay), RO (Romania), TZ (Tanzania), UG (Uganda), UM (United States Minor Outlying Islands),
 *        ZA (South Africa), ZM (Zambia).
 *    2. Chrome does not provide a dropdown list of states for 161 countries in total, out of the 249 countries WC supports,
 *        so the countries in which the state field is required by WC, and not provided by the browser are not supported.
 *    3. Chrome does not provide a zip/postal code field for 60 out of the 249 countries WC supports. Only for 5 countries
 *        the zip code field is missing while the state field is present: BS (Bahamas), PA (Panama), SC (Seychelles),
 *        SR (Suriname), TV (Tuvalu). Several other countries provide an optional zip code field.
 *    4. WC expects it, but there's no city field in the Payment Request API for the following:
 *        JP (Japan), MO (Macao), TN (Tunisia), KY (Cayman Islands), GI (Gibraltar).
 *    5. The following countries are not listed in WC:
 *        AC (Ascension Island), IC (Canary Islands), EA (Ceuta & Melilla), CP (Clipperton Island), DG (Diego Garcia),
 *        TA (Tristan da Cunha), XK (Kosovo).
 *
 * @since 5.1.0
 *
 * @deprecated 10.3.0 Moved to includes/constants/class-wc-stripe-express-checkout-button-states.php
 */
class WC_Stripe_Payment_Request_Button_States {
	/**
	 * A list of states which is compatible with Chromium based browsers for the Payment Request API.
	 * If the input comes from Chrome, we will always match with `code`, but if the request comes from
	 * Safari or other browsers which don't provide a dropdown list, we can match one of the following:
	 *    - [0] = code (string)
	 *    - [1] = name (string)
	 *    - [2] = localName (string|null)
	 */

	// phpcs:disable
	const STATES = WC_Stripe_Express_Checkout_Button_States::STATES;
	// phpcs:enable
}
