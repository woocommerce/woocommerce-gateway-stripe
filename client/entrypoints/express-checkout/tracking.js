/**
 * External dependencies
 */
import { debounce } from 'lodash';
import { recordUserEvent } from 'tracks';

// Track the button click event.
export const trackExpressCheckoutButtonClick = ( paymentMethod, source ) => {
	const expressPaymentTypeEvents = {
		google_pay: 'gpay_button_click',
		apple_pay: 'applepay_button_click',
	};

	const event = expressPaymentTypeEvents[ paymentMethod ];
	if ( ! event ) return;

	recordUserEvent( event, { source } );
};

// Track the button load event.
export const trackExpressCheckoutButtonLoad = debounce(
	( { paymentMethods, source } ) => {
		const expressPaymentTypeEvents = {
			googlePay: 'gpay_button_load',
			applePay: 'applepay_button_load',
		};

		for ( const paymentMethod of paymentMethods ) {
			const event = expressPaymentTypeEvents[ paymentMethod ];
			if ( ! event ) continue;

			recordUserEvent( event, { source } );
		}
	},
	1000
);
