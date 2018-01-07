/**
 * External dependencies
 */
import { By } from 'selenium-webdriver';
import { WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { WPAdminWCSettings } from 'wc-e2e-page-objects';

const ENABLE_SELECTOR = By.css( '#woocommerce_stripe_enabled' );
const TEST_PUBLISHABLE_KEY_SELECTOR = By.css( '#woocommerce_stripe_test_publishable_key' );
const TEST_PUBLISHABLE_SECRET_KEY_SELECTOR = By.css( '#woocommerce_stripe_test_publishable_secret_key' );


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
		return helper.setWhenSettable( this.driver, TEST_PUBLISHABLE_SECRET_KEY_SELECTOR, testPublishableSecret );
	}
}
