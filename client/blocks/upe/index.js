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

// // Register Express Checkout Elements.
// if (
// 	getBlocksConfiguration()?.isAmazonPayAvailable && // Hide behind feature flag so the editor does not show the button.
// 	getBlocksConfiguration()?.isAmazonPayEnabled
// ) {
// 	registerExpressPaymentMethod( expressCheckoutElementAmazonPay( api ) );
// }
// if ( getBlocksConfiguration()?.isPaymentRequestEnabled ) {
// 	registerExpressPaymentMethod( expressCheckoutElementApplePay( api ) );
// 	registerExpressPaymentMethod( expressCheckoutElementGooglePay( api ) );
// }
// if ( getBlocksConfiguration()?.isLinkEnabled ) {
// 	registerExpressPaymentMethod( expressCheckoutElementStripeLink( api ) );
// }

// Update token labels when the checkout form is loaded.
updateTokenLabelsWhenLoaded();

// Add order attribution inputs to the page.
addOrderAttributionInputsIfNotExists();

// Populate order attribution inputs with order tracking data.
populateOrderAttributionInputs();

callWhenElementIsAvailable(
	'label[for="radio-control-wc-payment-method-options-stripe"]',
	function () {
		const paymentMethodRadios = document.querySelectorAll(
			'input[name="radio-control-wc-payment-method-options"]'
		);
		if ( paymentMethodRadios.length === 0 ) {
			return;
		}

		function handleStyleChange( value ) {
			const stripeLabel = document.querySelector(
				'label[for="radio-control-wc-payment-method-options-stripe"]'
			);
			const highlightedOption = document.querySelector(
				'.wc-block-components-radio-control-accordion-option--checked-option-highlighted'
			);
			if ( value === 'stripe' ) {
				stripeLabel.style.display = 'none';
				if ( highlightedOption ) {
					highlightedOption.style.boxShadow = 'none';
				}
			} else {
				stripeLabel.style.display = 'block';
				if ( highlightedOption ) {
					highlightedOption.style.boxShadow =
						'inset 0 0 0 1.5px currentColor';
				}
			}
		}

		// Add event listeners to payment method radio buttons to hide/show the Stripe option label when selected/deselected.
		paymentMethodRadios.forEach( ( button ) => {
			handleStyleChange( button.value );
			button.addEventListener( 'change', function () {
				handleStyleChange( this.value );
			} );
		} );

		// If there is more than one payment method option, we won't auto-select the Stripe option to avoid overriding the customer's choice. We only want to auto-select Stripe if it's the only available payment method.
		if ( paymentMethodRadios.length > 1 ) {
			return;
		}
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
