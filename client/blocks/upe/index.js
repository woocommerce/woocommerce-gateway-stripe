import {
	registerPaymentMethod,
	registerExpressPaymentMethod,
} from '@woocommerce/blocks-registry';
import {
	PAYMENT_METHOD_AMAZON_PAY,
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_GIROPAY,
	PAYMENT_METHOD_LINK,
} from '../../stripe-utils/constants';
import { updateTokenLabelsWhenLoaded } from './token-label-updater.js';
import paymentRequestPaymentMethod from 'wcstripe/blocks/payment-request';
import {
	expressCheckoutElementAmazonPay,
	expressCheckoutElementApplePay,
	expressCheckoutElementGooglePay,
	expressCheckoutElementStripeLink,
} from 'wcstripe/blocks/express-checkout';
import WCStripeAPI from 'wcstripe/api';
import {
	addOrderAttributionInputsIfNotExists,
	getBlocksConfiguration,
	populateOrderAttributionInputs,
} from 'wcstripe/blocks/utils';
import './styles.scss';
import { upeElement } from 'wcstripe/blocks/upe/upe-element';

const api = new WCStripeAPI(
	getBlocksConfiguration(),
	// A promise-based interface to jQuery.post.
	( url, args ) => {
		return new Promise( ( resolve, reject ) => {
			jQuery.post( url, args ).then( resolve ).fail( reject );
		} );
	}
);

const paymentMethodsConfig =
	getBlocksConfiguration()?.paymentMethodsConfig ?? {};

const methodsToFilter = [
	PAYMENT_METHOD_AMAZON_PAY,
	PAYMENT_METHOD_LINK,
	PAYMENT_METHOD_GIROPAY, // Skip giropay as it was deprecated by Jun, 30th 2024.
];

// Register UPE Elements.
if ( getBlocksConfiguration()?.isSPEEnabled ) {
	registerPaymentMethod(
		upeElement( PAYMENT_METHOD_CARD, api, paymentMethodsConfig.card )
	);
} else {
	Object.entries( paymentMethodsConfig )
		.filter( ( [ method ] ) => ! methodsToFilter.includes( method ) )
		.forEach( ( [ method, config ] ) => {
			registerPaymentMethod( upeElement( method, api, config ) );
		} );
}

if ( getBlocksConfiguration()?.isECEEnabled ) {
	// Register Express Checkout Elements.
	if (
		getBlocksConfiguration()?.isAmazonPayAvailable && // Hide behind feature flag so the editor does not show the button.
		getBlocksConfiguration()?.isAmazonPayEnabled
	) {
		registerExpressPaymentMethod( expressCheckoutElementAmazonPay( api ) );
	}
	if ( getBlocksConfiguration()?.isPaymentRequestEnabled ) {
		registerExpressPaymentMethod( expressCheckoutElementApplePay( api ) );
		registerExpressPaymentMethod( expressCheckoutElementGooglePay( api ) );
	}
	if ( getBlocksConfiguration()?.isLinkEnabled ) {
		registerExpressPaymentMethod( expressCheckoutElementStripeLink( api ) );
	}
} else {
	// Register Stripe Payment Request.
	// TODO: We can remove this once we're sure everyone on the new checkout (UPE) has been migrated to ECE.
	registerExpressPaymentMethod( paymentRequestPaymentMethod );
}

// Update token labels when the checkout form is loaded.
updateTokenLabelsWhenLoaded();

// Add order attribution inputs to the page.
addOrderAttributionInputsIfNotExists();

// Populate order attribution inputs with order tracking data.
populateOrderAttributionInputs();
