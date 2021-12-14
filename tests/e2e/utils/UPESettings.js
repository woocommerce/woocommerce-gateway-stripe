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
	 * Activates Upe using the new settings page
	 */
	activateUpe: async () => {
		await stripeUPESettingsUtils.openSettingsPage();
		await buttonsUtils.clickButtonWithText( 'Enable in your store' );
		await page.waitForSelector( '#wc-stripe-onboarding-wizard-container' );

		await buttonsUtils.clickButtonWithText( 'Enable' );
		await page.waitForSelector(
			'.add-payment-methods-task__payment-selector-title',
			{ visible: true }
		);

		await buttonsUtils.clickButtonWithText( 'Add payment methods' );
		await page.waitForSelector(
			'.setup-complete-task__enabled-methods-list',
			{ visible: true }
		);

		await buttonsUtils.clickButtonWithText( 'Go to Stripe settings' );
		await page.waitForSelector( '#wc-stripe-account-settings-container', {
			visible: true,
		} );

		await expect( page ).not.toMatch(
			'Enable the new Stripe checkout experience'
		);
		await expect( page ).toMatch( 'giropay' );
	},

	/**
	 * Deactivates Upe using the new settings page
	 */
	deactivateUpe: async () => {
		await stripeUPESettingsUtils.openSettingsPage();
		await buttonsUtils.clickButtonWithText( 'Payment methods menu' );
		await buttonsUtils.clickButtonWithText(
			'Disable',
			'//*[@class="components-dropdown-menu__menu"]'
		);
		await buttonsUtils.clickButtonWithText(
			'Disable',
			'//*[@class="wcstripe-confirmation-modal__footer"]'
		);

		await expect( page ).toMatch(
			'Enable the new Stripe checkout experience'
		);
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
		const [ checkbox ] = await page.$x(
			'//input[@name="' + methodName + '"]'
		);

		if ( ! checkbox ) {
			throw new Error( 'Method not found' );
		}

		await checkbox.click();
	},
};
