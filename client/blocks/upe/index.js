import {
	registerPaymentMethod,
	registerExpressPaymentMethod,
} from '@woocommerce/blocks-registry';
//import upePaymentMethod from './payment-method';
import { getDeferredIntentCreationUPEFields } from './upe-deferred-intent-creation/payment-elements.js';
import { SavedTokenHandler } from './saved-token-handler';
import paymentRequestPaymentMethod from 'wcstripe/blocks/payment-request';
import WCStripeAPI from 'wcstripe/api';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { getStripeServerData } from 'wcstripe/stripe-utils';

// Register Stripe UPE.
//registerPaymentMethod( upePaymentMethod );

const upeMethods = {
	card: 'woocommerce_stripe',
	bancontact: 'woocommerce_stripe_bancontact',
	au_becs_debit: 'woocommerce_stripe_au_becs_debit',
	eps: 'woocommerce_stripe_eps',
	giropay: 'woocommerce_stripe_giropay',
	ideal: 'woocommerce_stripe_ideal',
	p24: 'woocommerce_stripe_p24',
	sepa_debit: 'woocommerce_stripe_sepa_debit',
	sofort: 'woocommerce_stripe_sofort',
	affirm: 'woocommerce_stripe_affirm',
	afterpay_clearpay: 'woocommerce_stripe_afterpay_clearpay',
};

const api = new WCStripeAPI(
	getStripeServerData(),
	// A promise-based interface to jQuery.post.
	( url, args ) => {
		return new Promise( ( resolve, reject ) => {
			jQuery.post( url, args ).then( resolve ).fail( reject );
		} );
	}
);

Object.entries( getBlocksConfiguration()?.paymentMethodsConfig )
	.filter( ( [ upeName ] ) => upeName !== 'link' )
	.forEach( ( [ upeName, upeConfig ] ) => {
		registerPaymentMethod( {
			name: upeMethods[ upeName ],
			content: getDeferredIntentCreationUPEFields(
				upeName,
				upeMethods,
				api
			),
			edit: getDeferredIntentCreationUPEFields(
				upeName,
				upeMethods,
				api
			),
			savedTokenComponent: <SavedTokenHandler api={ api } />,
			canMakePayment: () => !! api.getStripe(),
			paymentMethodId: upeMethods[ upeName ],
			// see .wc-block-checkout__payment-method styles in blocks/style.scss
			label: upeConfig.title,
			ariaLabel: 'Stripe',
			supports: {
				// Use `false` as fallback values in case server provided configuration is missing.
				showSavedCards:
					getBlocksConfiguration()?.showSavedCards ?? false,
				showSaveOption:
					getBlocksConfiguration()?.showSaveOption ?? false,
				features: getBlocksConfiguration()?.supports ?? [],
			},
		} );
	} );

// Register Stripe Payment Request.
registerExpressPaymentMethod( paymentRequestPaymentMethod );
