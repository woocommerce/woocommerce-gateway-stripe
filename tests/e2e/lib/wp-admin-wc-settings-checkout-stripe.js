/**
 * External dependencies
 */
import { By } from 'selenium-webdriver';
import { WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { WPAdminWCSettings } from 'wc-e2e-page-objects';

const ENABLE_SELECTOR = By.css( '#woocommerce_stripe_enabled' );
const TEST_PUBLISHABLE_KEY_SELECTOR = By.css( '#woocommerce_stripe_test_publishable_key' );
const TEST_PUBLISHABLE_SECRET_KEY_SELECTOR = By.css( '#woocommerce_stripe_test_publishable_secret_key' );
const ENABLE_TEST_MODE_SELECTOR = By.css( '#woocommerce_stripe_testmode' );
const INLINE_FORM_SELECTOR = By.css( '#woocommerce_stripe_inline_cc_form' );
const STATEMENT_DESCRIPTOR_SELECTOR = By.css( '#woocommerce_stripe_statement_descriptor' );
const CAPTURE_SELECTOR = By.css( '#woocommerce_stripe_capture' );
const REQUIRE_3DS_SELECTOR = By.css( '#woocommerce_stripe_three_d_secure' );
const STRIPE_CHECKOUT_SELECTOR = By.css( '#woocommerce_stripe_stripe_checkout' );
const PAYMENT_REQUEST_SELECTOR = By.css( '#woocommerce_stripe_payment_request' );
const SAVED_CARDS_SELECTOR = By.css( '#woocommerce_stripe_saved_cards' );
const LOGGING_SELECTOR = By.css( '#woocommerce_stripe_logging' );
const ENABLE_BANCONTACT_SELECTOR = By.css( '#woocommerce_stripe_bancontact_enabled' );


export default class WPAdminWCSettingsCheckoutStripe extends WPAdminWCSettings {
	constructor( driver, args = {} ) {
		super( driver, args );
	}

	checkEnable() {
		return helper.setCheckbox( this.driver, ENABLE_SELECTOR );
	}

	uncheckEnable() {
		return helper.unsetCheckbox( this.driver, ENABLE_SELECTOR );
	}

	setTestPublishableKey( testPublishableKey ) {
		return helper.setWhenSettable( this.driver, TEST_PUBLISHABLE_KEY_SELECTOR, testPublishableKey );
	}

	setTestPublishableSecretKey( testPublishableSecretKey ) {
		return helper.setWhenSettable( this.driver, TEST_PUBLISHABLE_SECRET_KEY_SELECTOR, testPublishableSecretKey );
	}

	setStatementDescriptor( statementDescriptor ) {
		return helper.setWhenSettable( this.driver, STATEMENT_DESCRIPTOR_SELECTOR, statementDescriptor );
	}

	enableTestMode() {
		return helper.setCheckbox( this.driver, ENABLE_TEST_MODE_SELECTOR );
	}

	disableTestMode() {
		return helper.unsetCheckbox( this.driver, ENABLE_TEST_MODE_SELECTOR );
	}

	enableInlineForm() {
		return helper.setCheckbox( this.driver, INLINE_FORM_SELECTOR );
	}

	disableInlineForm() {
		return helper.unsetCheckbox( this.driver, INLINE_FORM_SELECTOR );
	}

	enableStripeCheckout() {
		return helper.unsetCheckbox( this.driver, STRIPE_CHECKOUT_SELECTOR );
	}

	disableStripeCheckout() {
		return helper.unsetCheckbox( this.driver, STRIPE_CHECKOUT_SELECTOR );
	}

	enableSavedCards() {
		return helper.setCheckbox( this.driver, SAVED_CARDS_SELECTOR );
	}

	disableSavedCards() {
		return helper.unsetCheckbox( this.driver, SAVED_CARDS_SELECTOR );
	}

	enableLogging() {
		return helper.setCheckbox( this.driver, LOGGING_SELECTOR );
	}

	disableLogging() {
		return helper.unsetCheckbox( this.driver, LOGGING_SELECTOR );
	}
}
