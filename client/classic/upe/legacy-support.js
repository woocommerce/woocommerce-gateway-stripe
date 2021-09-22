import $ from 'jquery';
import { getStripeServerData } from 'wcstripe/stripe-utils';

/**
 * Handles hashchange events when using PRBs.
 *
 * @param {Object}   api        The Stripe API interface.
 * @param {Function} showError  Function used to present an error to the customer.
 */
export const legacyHashchangeHandler = ( api, showError ) => {
	const partials = window.location.hash.match(
		/^#?confirm-(pi|si)-([^:]+):(.+)$/
	);

	if ( ! partials || partials.length < 4 ) {
		return;
	}

	const type = partials[ 1 ];
	const intentClientSecret = partials[ 2 ];
	const redirectURL = decodeURIComponent( partials[ 3 ] );

	// Cleanup the URL.
	// https://stackoverflow.com/a/5298684
	// eslint-disable-next-line no-undef
	history.replaceState(
		'',
		document.title,
		window.location.pathname + window.location.search
	);
	api.getStripe()
		[ type === 'si' ? 'handleCardSetup' : 'handleCardPayment' ](
			intentClientSecret
		)
		.then( function ( response ) {
			if ( response.error ) {
				throw response.error;
			}

			const intent =
				response[ type === 'si' ? 'setupIntent' : 'paymentIntent' ];
			if (
				intent.status !== 'requires_capture' &&
				intent.status !== 'succeeded'
			) {
				return;
			}

			window.location = redirectURL;
		} )
		.catch( function ( error ) {
			$( 'form.checkout' ).removeClass( 'processing' ).unblock();
			$( '#order_review' ).removeClass( 'processing' ).unblock();
			$( '#payment' ).show( 500 );

			let errorMessage = error.message;

			// If this is a generic error, we probably don't want to display the error message to the user,
			// so display a generic message instead.
			if ( error instanceof Error ) {
				errorMessage = getStripeServerData()?.genericErrorMessage;
			}

			showError( errorMessage );

			// Report back to the server.
			$.get( redirectURL + '&is_ajax' );
		} );
};
