import $ from 'jquery';
import {
	updateShippingOptions,
	updateShippingDetails,
	createOrder,
} from 'wcstripe/api/blocks';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

const shippingAddressChangeHandler = ( paymentRequestType ) => ( evt ) => {
	const { shippingAddress } = evt;

	// Update the payment request shipping information address.
	updateShippingOptions( shippingAddress, paymentRequestType ).then(
		( response ) => {
			evt.updateWith( {
				status: response.result,
				shippingOptions: response.shipping_options,
				total: response.total,
				displayItems: response.displayItems,
			} );
		}
	);
};

const shippingOptionChangeHandler = ( evt ) => {
	const { shippingOption } = evt;

	// Update the shipping rates for the order.
	updateShippingDetails( shippingOption ).then( ( response ) => {
		if ( response.result === 'success' ) {
			evt.updateWith( {
				status: 'success',
				total: response.total,
				displayItems: response.displayItems,
			} );
		}

		if ( response.result === 'fail' ) {
			evt.updateWith( { status: 'fail' } );
		}
	} );
};

/**
 * Helper function. Returns payment intent information from the provided URL.
 * If no information is embedded in the URL this function returns `undefined`.
 *
 * @param {string} url - The url to check for partials.
 *
 * @return {Object|undefined} The object containing `type`, `clientSecret`, and
 *                            `redirectUrl`. Undefined if no partails embedded in the url.
 */
const getRedirectUrlPartials = ( url ) => {
	const partials = url.match( /^#?confirm-(pi|si)-([^:]+):(.+)$/ );

	if ( ! partials || partials.length < 4 ) {
		return undefined;
	}

	const type = partials[ 1 ];
	const clientSecret = partials[ 2 ];
	const redirectUrl = decodeURIComponent( partials[ 3 ] );

	return {
		type,
		clientSecret,
		redirectUrl,
	};
};

/**
 * Helper function. Requests that the provided intent (identified by the secret) is be
 * handled by Stripe. Returns a promise from Stripe.
 *
 * @param {Object} stripe - The stripe object.
 * @param {string} intentType - The type of intent. Either `pi` or `si`.
 * @param {string} clientSecret - Client secret returned from Stripe.
 *
 * @return {Promise} A promise from Stripe with the confirmed intent or an error.
 */
const requestIntentConfirmation = ( stripe, intentType, clientSecret ) => {
	const isSetupIntent = intentType === 'si';

	if ( isSetupIntent ) {
		return stripe.handleCardSetup( clientSecret );
	}
	return stripe.handleCardPayment( clientSecret );
};

/**
 * Helper function. Returns the payment or setup intent from a given confirmed intent.
 *
 * @param {Object} intent - The confirmed intent.
 * @param {string} intentType - The payment intent's type. Either `pi` or `si`.
 *
 * @return {Object} The Stripe payment or setup intent.
 */
const getIntentFromConfirmation = ( intent, intentType ) => {
	const isSetupIntent = intentType === 'si';

	if ( isSetupIntent ) {
		return intent.setupIntent;
	}
	return intent.paymentIntent;
};

const doesIntentRequireCapture = ( intent ) => {
	return intent.status === 'requires_capture';
};

const didIntentSucceed = ( intent ) => {
	return intent.status === 'succeeded';
};

/**
 * Helper function; part of a promise chain.
 * Receives a possibly confirmed payment intent from Stripe and proceeds to charge the
 * payment method of the intent was confirmed successfully.
 *
 * @param {string} redirectUrl - The URL to redirect to after a successful payment.
 * @param {string} intentType - The type of the payment intent. Either `pi` or `si`.
 */
const handleIntentConfirmation = ( redirectUrl, intentType ) => (
	confirmation
) => {
	if ( confirmation.error ) {
		throw confirmation.error;
	}

	const intent = getIntentFromConfirmation( confirmation, intentType );
	if ( doesIntentRequireCapture( intent ) || didIntentSucceed( intent ) ) {
		// If the 3DS verification was successful we can proceed with checkout as usual.
		window.location = redirectUrl;
	}
};

/**
 * Helper function; part of a promise chain.
 * Receives the response from our server after we attempt to create an order through
 * our AJAX API, proceeds with payment if possible, otherwise attempts to confirm the
 * payment (i.e. 3DS verification) through Stripe.
 *
 * @param {Object} stripe - The Stripe JS object.
 * @param {Object} evt - The `source` event from the Stripe payment request button.
 * @param {Function} setExpressPaymentError - Used to show error messages to the customer.
 */
const performPayment = ( stripe, evt, setExpressPaymentError ) => (
	createOrderResponse
) => {
	if ( createOrderResponse.result === 'success' ) {
		evt.complete( 'success' );

		const partials = getRedirectUrlPartials( createOrderResponse.redirect );

		// If no information is embedded in the URL that means the payment doesn't need
		// verification and we can proceed as usual.
		if ( ! partials || partials.length < 4 ) {
			window.location = createOrderResponse.redirect;
			return;
		}

		const { type, clientSecret, redirectUrl } = partials;

		// The payment requires 3DS verification, so we try to take care of that here.
		requestIntentConfirmation( stripe, type, clientSecret )
			.then( handleIntentConfirmation( redirectUrl, type ) )
			.catch( ( error ) => {
				setExpressPaymentError( error.message );

				// Report back to the server.
				$.get( redirectUrl + '&is_ajax' );
			} );
	} else {
		evt.complete( 'fail' );

		// WooCommerce returns a messege embedded in a notice via HTML here, so we need
		// to extract the actual message from the notice.
		const div = document.createElement( 'div' );
		div.innerHTML = createOrderResponse.messages;
		const errorMessage = div?.firstChild?.textContent ?? '';

		setExpressPaymentError( errorMessage );
	}
};

const paymentProcessingHandler = (
	stripe,
	paymentRequestType,
	setExpressPaymentError
) => ( evt ) => {
	const allowPrepaidCards =
		getBlocksConfiguration()?.stripe?.allow_prepaid_card === 'yes';

	// Check if we allow prepaid cards.
	if ( ! allowPrepaidCards && evt?.source?.card?.funding === 'prepaid' ) {
		setExpressPaymentError(
			getBlocksConfiguration()?.i18n?.no_prepaid_card
		);
	} else {
		// Create the order and attempt to pay.
		createOrder( evt, paymentRequestType ).then(
			performPayment( stripe, evt, setExpressPaymentError )
		);
	}
};

export {
	shippingAddressChangeHandler,
	shippingOptionChangeHandler,
	paymentProcessingHandler,
};
