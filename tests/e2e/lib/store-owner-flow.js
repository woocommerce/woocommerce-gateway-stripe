/**
 * External dependencies
 */
import { StoreOwnerFlow as Base } from 'wc-e2e-page-objects';

/**
 * Internal dependencies
 */
import WPAdminWCSettingsCheckoutStripe from './wp-admin-wc-settings-checkout-stripe.js';

export default class StoreOwnerFlow extends Base {
	constructor( driver, args = {} ) {
		super( driver, args );
	}

	openStripeSettings() {
		return this.open( {
			path: '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=stripe',
			object: WPAdminWCSettingsCheckoutStripe
		} );
	}

	setStripeSettings( args ) {
		args = Object.assign(
			{
				enable: true,
			},
			args
		);

		const settings = this.openStripeSettings();
		if ( args.enable ) {
			settings.checkEnable();
		}

		if ( args.testPublishableKey ) {
			settings.setTestPublishableKey( args.testPublishableKey );
		}

		if ( args.testPublishableSecret ) {
			settings.setTestPublishableSecret( args.testPublishableSecret );
		}

		if ( args.statementDescriptor ) {
			settings.setStatementDescriptor( args.statementDescriptor );
		}

		if ( args.enableTestMode ) {
			settings.enableTestMode();
		} else {
			settings.disableTestMode();
		}

		if ( args.enableInlineForm ) {
			settings.enableInlineForm();
		} else {
			settings.disableInlineForm();
		}

		if ( args.enableStripeCheckout ) {
			settings.enableStripeCheckout();
		} else {
			settings.disableStripeCheckout();
		}

		if ( args.enableSavedCards ) {
			settings.enableSavedCards();
		} else {
			settings.disableSavedCards();
		}

		if ( args.enableLogging ) {
			settings.enableLogging();
		} else {
			settings.disableLogging();
		}

		return settings.saveChanges();
	}
}
