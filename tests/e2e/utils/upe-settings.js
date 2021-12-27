import config from 'config';
import { buttonsUtils } from './buttons';
import { withRestApi } from '@woocommerce/e2e-utils/build/flows/with-rest-api';
import { factories } from '@woocommerce/e2e-utils';

const client = factories.api.withDefaultPermalinks;

const baseUrl = config.get( 'url' );

const UPE_SETTINGS_PAGE =
	baseUrl + 'wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe';

export const stripeUPESettingsUtils = {
	/**
	 * Resets Stripe and store settings to always run tests with the same starting point
	 */
	resetSettings: async () => {
		await withRestApi.deleteCustomerByEmail(
			config.get( 'addresses.customer.billing.email' )
		);
		await withRestApi.updateSettingOption(
			'general',
			'woocommerce_currency',
			{ value: 'USD' }
		);
		await withRestApi.updateSettingOption(
			'general',
			'woocommerce_default_country',
			{ value: 'US:CA' }
		);

		let response = await client.post( 'wc/v3/wc_stripe/settings', {
			is_stripe_enabled: true,
			is_test_mode_enabled: true,
			title: '',
			title_upe: '',
			description: '',
			enabled_payment_method_ids: [ 'card' ],
			available_payment_method_ids: [ 'card' ],
			is_payment_request_enabled: true,
			payment_request_button_type: 'buy',
			payment_request_button_theme: 'dark',
			payment_request_button_size: 'default',
			payment_request_button_locations: [ 'product', 'cart', 'checkout' ],
			is_manual_capture_enabled: false,
			is_saved_cards_enabled: true,
			is_separate_card_form_enabled: true,
			statement_descriptor: 'wcstripe',
			is_short_statement_descriptor_enabled: false,
			short_statement_descriptor: '',
			is_debug_log_enabled: false,
			is_upe_enabled: false,
		} );
		expect( response.statusCode ).toEqual( 200 );

		response = await client.post(
			'wc/v3/wc_stripe/account_keys',
			config.get( 'stripe' )
		);
		expect( response.statusCode ).toEqual( 200 );
	},

	/**
	 * Opens Upe settings page
	 */
	openSettingsPage: async () => {
		await page.goto( UPE_SETTINGS_PAGE, {
			waitUntil: 'networkidle0',
		} );
	},

	/**
	 * Toggles UPE between active and inactive state
	 */
	toggleUpe: async () => {
		await stripeUPESettingsUtils.openSettingsPage();
		await buttonsUtils.clickButtonWithText(
			'Settings',
			'//*[@id="wc-stripe-account-settings-container"]'
		);
		await buttonsUtils.clickButtonWithText(
			'Advanced settings',
			'//*[@id="wc-stripe-account-settings-container"]'
		);
		await buttonsUtils.toggleCheckbox(
			'//input[@data-testid="new-checkout-experience-checkbox"]'
		);
		await buttonsUtils.clickButtonWithText( 'Save changes' );
		await expect( page ).toMatch( 'Settings saved.' );
		await stripeUPESettingsUtils.openSettingsPage();
	},

	/**
	 * Activates Upe using the new settings page
	 */
	activateUpe: async () => {
		await stripeUPESettingsUtils.toggleUpe();
		await expect( page ).toMatch( 'giropay' );
	},

	/**
	 * Deactivates Upe using the new settings page
	 */
	deactivateUpe: async () => {
		await stripeUPESettingsUtils.toggleUpe();
		await expect( page ).not.toMatch( 'giropay' );
	},

	/**
	 * Activates a UPE payment method
	 * @param methodName checkbox input name
	 */
	activatePaymentMethod: async ( methodName ) => {
		await stripeUPESettingsUtils.openSettingsPage();
		await stripeUPESettingsUtils.clickOnPaymentMethodCheckbox( methodName );
		await buttonsUtils.clickButtonWithText( 'Save changes' );
	},

	/**
	 * Deactivates a UPE payment method
	 * @param methodName checkbox input name
	 */
	deactivatePaymentMethod: async ( methodName ) => {
		await stripeUPESettingsUtils.openSettingsPage();
		await stripeUPESettingsUtils.clickOnPaymentMethodCheckbox( methodName );
		await buttonsUtils.clickButtonWithText( 'Remove' );
		await buttonsUtils.clickButtonWithText( 'Save changes' );
	},

	/**
	 * Clicks on the payment method checkbox
	 * @param methodName checkbox input name
	 */
	clickOnPaymentMethodCheckbox: async ( methodName ) => {
		await buttonsUtils.toggleCheckbox(
			'//input[@name="' + methodName + '"]'
		);
	},
};
