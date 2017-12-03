/**
 * External dependencies
 */
import { StoreOwnerFlow as Base } from 'wc-e2e-page-objects';

/**
 * Internal dependencies
 */


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
				useSandbox: true
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
		if ( args.useSandbox ) {
			settings.checkUseSandbox();
		}
		if ( args.paymentCapture ) {
			settings.selectPaymentCapture( args.paymentCapture );
		}

		return settings.saveChanges();
	}
}
