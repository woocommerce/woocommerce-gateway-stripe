import config from 'config';
import { buttons } from './buttons';

const baseUrl = config.get( 'url' );

const UPE_SETTINGS_PAGE =
	baseUrl + 'wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe';

export const stripeSettings = {
	/**
	 * Opens Upe settings page
	 */
	openUpeSettingsPage: async () => {
		await page.goto( UPE_SETTINGS_PAGE, {
			waitUntil: 'networkidle0',
		} );
	},

	/**
	 * Activates Upe using the new settings page
	 */
	activateUpe: async () => {
		await stripeSettings.openUpeSettingsPage();
		await buttons.clickButtonWithText( 'Enable in your store' );
		await page.waitForSelector( '#wc-stripe-onboarding-wizard-container' );

		await buttons.clickButtonWithText( 'Enable' );
		await page.waitForSelector(
			'.add-payment-methods-task__payment-selector-title',
			{ visible: true }
		);

		await buttons.clickButtonWithText( 'Add payment methods' );
		await page.waitForSelector(
			'.setup-complete-task__enabled-methods-list',
			{ visible: true }
		);

		await buttons.clickButtonWithText( 'Go to Stripe settings' );
		await page.waitForSelector( '#wc-stripe-account-settings-container', {
			visible: true,
		} );
	},
};
