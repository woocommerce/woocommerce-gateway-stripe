import { debounce } from 'lodash';
import { recordEvent } from 'wcstripe/tracking';

// Track the button click event.
export const trackExpressCheckoutButtonClick = ( paymentMethod, source ) => {
	const expressPaymentTypeEvents = {
		google_pay: 'gpay_button_click',
		apple_pay: 'applepay_button_click',
		link: 'link_button_click',
	};

	const event = expressPaymentTypeEvents[ paymentMethod ];
	if ( ! event ) {
		return;
	}

	recordEvent( event, { source } );
};

// Track the button load event.
export const trackExpressCheckoutButtonLoad = debounce(
	( { paymentMethods, source } ) => {
		const expressPaymentTypeEvents = {
			googlePay: 'gpay_button_load',
			applePay: 'applepay_button_load',
			link: 'link_button_load',
		};

		for ( const paymentMethod of paymentMethods ) {
			const event = expressPaymentTypeEvents[ paymentMethod ];
			if ( ! event ) {
				continue;
			}

			recordEvent( event, { source } );
		}
	},
	1000
);
