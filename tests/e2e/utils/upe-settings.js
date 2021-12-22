import config from 'config';
import { buttonsUtils } from './buttons';

const baseUrl = config.get( 'url' );

const UPE_SETTINGS_PAGE =
	baseUrl + 'wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe';

export const stripeUPESettingsUtils = {
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
