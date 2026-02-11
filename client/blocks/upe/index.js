import {
	registerPaymentMethod,
	registerExpressPaymentMethod,
} from '@woocommerce/blocks-registry';
import {
	PAYMENT_METHOD_AFFIRM,
	PAYMENT_METHOD_AMAZON_PAY,
	PAYMENT_METHOD_GIROPAY,
	PAYMENT_METHOD_KLARNA,
	PAYMENT_METHOD_LINK,
} from '../../stripe-utils/constants';
import { updateTokenLabelsWhenLoaded } from './token-label-updater.js';
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
import { callWhenElementIsAvailable } from 'wcstripe/blocks/upe/call-when-element-is-available';

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

// Filter out some BNPLs when other official extensions are present.
if ( getBlocksConfiguration()?.hasAffirmGatewayPlugin ) {
	methodsToFilter.push( PAYMENT_METHOD_AFFIRM );
}
if ( getBlocksConfiguration()?.hasKlarnaGatewayPlugin ) {
	methodsToFilter.push( PAYMENT_METHOD_KLARNA );
}

Object.entries( paymentMethodsConfig )
	.filter( ( [ method ] ) => ! methodsToFilter.includes( method ) )
	.forEach( ( [ method, config ] ) => {
		registerPaymentMethod( upeElement( method, api, config ) );
	} );

// Register Express Checkout Elements.
if (
	getBlocksConfiguration()?.isAmazonPayAvailable && // Hide behind feature flag so the editor does not show the button.
	getBlocksConfiguration()?.isAmazonPayEnabled
) {
	registerExpressPaymentMethod( expressCheckoutElementAmazonPay( api ) );
}
if ( getBlocksConfiguration()?.isExpressCheckoutEnabled ) {
	registerExpressPaymentMethod( expressCheckoutElementApplePay( api ) );
	registerExpressPaymentMethod( expressCheckoutElementGooglePay( api ) );
}
if ( getBlocksConfiguration()?.isLinkEnabled ) {
	registerExpressPaymentMethod( expressCheckoutElementStripeLink( api ) );
}

// Update token labels when the checkout form is loaded.
updateTokenLabelsWhenLoaded();

// Add order attribution inputs to the page.
addOrderAttributionInputsIfNotExists();

// Populate order attribution inputs with order tracking data.
populateOrderAttributionInputs();

callWhenElementIsAvailable(
	'label[for="radio-control-wc-payment-method-options-stripe"]',
	function () {
		callWhenElementIsAvailable(
			'#radio-control-wc-payment-method-options-stripe__content > div:nth-child(6) > div > iframe:nth-child(1)',
			function () {
				const iframeElement = document.querySelector(
					'#radio-control-wc-payment-method-options-stripe__content > div:nth-child(6) > div > iframe:nth-child(1)'
				);
				iframeElement.onload = function () {
					const iframeDocument =
						iframeElement.contentDocument ||
						iframeElement.contentWindow.document;

					// TODO: Stirpe blocks the reading of the iframe content

					const accordionItems =
						iframeDocument.querySelectorAll( '.p-AccordionItem' );

					console.log( accordionItems );
				};
			}
		);

		const clickEvent = new MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
			view: window,
		} );

		const stripeLabel = document.querySelector(
			'label[for="radio-control-wc-payment-method-options-stripe"]'
		);

		stripeLabel.dispatchEvent( clickEvent );
	}
);
