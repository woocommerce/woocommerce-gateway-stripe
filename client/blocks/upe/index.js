import {
	registerPaymentMethod,
	registerExpressPaymentMethod,
} from '@woocommerce/blocks-registry';
import { getDeferredIntentCreationUPEFields } from './upe-deferred-intent-creation/payment-elements.js';
import { SavedTokenHandler } from './saved-token-handler';
import paymentRequestPaymentMethod from 'wcstripe/blocks/payment-request';
import WCStripeAPI from 'wcstripe/api';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { getStripeServerData } from 'wcstripe/stripe-utils';

// Register Stripe UPE.
const upeMethods = {
	card: 'stripe',
	bancontact: 'stripe_bancontact',
	au_becs_debit: 'stripe_au_becs_debit',
	eps: 'stripe_eps',
	giropay: 'stripe_giropay',
	ideal: 'stripe_ideal',
	p24: 'stripe_p24',
	sepa_debit: 'stripe_sepa_debit',
	sofort: 'stripe_sofort',
	affirm: 'stripe_affirm',
	afterpay_clearpay: 'stripe_afterpay_clearpay',
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
				api,
				upeConfig.testingInstructions
			),
			edit: getDeferredIntentCreationUPEFields(
				upeName,
				upeMethods,
				api,
				upeConfig.testingInstructions
			),
			savedTokenComponent: <SavedTokenHandler api={ api } />,
			canMakePayment: () => !! api.getStripe(),
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
