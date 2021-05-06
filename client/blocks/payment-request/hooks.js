/* global wc_stripe_payment_request_params */

/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import $ from 'jquery';

/**
 * Internal dependencies
 */
import {
	getCartDetails,
	createPaymentRequest,
	updateShippingOptions,
	updateShippingDetails,
	createOrder,
} from '../../api';

export const usePaymentRequest = ( stripe ) => {
	const [ paymentRequest, setPaymentRequest ] = useState( null );
	const [ paymentRequestType, setPaymentRequestType ] = useState( null );
	useEffect( () => {
		// Do nothing if Stripe object isn't loaded or paymentRequest already exists.
		if ( ! stripe || paymentRequest ) {
			return;
		}

		getCartDetails().then( ( cart ) => {
			const pr = createPaymentRequest( stripe, cart );

			pr.canMakePayment().then( ( result ) => {
				if ( result ) {
					setPaymentRequest( pr );
					setPaymentRequestType(
						result.applePay ? 'apple_pay' : 'payment_request_api'
					);
				}
			} );
		} );
	}, [ paymentRequest, stripe ] );
	return [ paymentRequest, paymentRequestType ];
};

export const useShippingAddressUpdateHandler = (
	paymentRequest,
	paymentRequestType
) => {
	useEffect( () => {
		// Need to use `?.` here in case paymentRequest is null.
		const shippingAddressUpdateHandler = paymentRequest?.on(
			'shippingaddresschange',
			( evt ) => {
				const { shippingAddress } = evt;
				updateShippingOptions(
					shippingAddress,
					paymentRequestType
				).then( ( response ) => {
					evt.updateWith( {
						status: response.result,
						shippingOptions: response.shipping_options,
						total: response.total,
						displayItems: response.displayItems,
					} );
				} );
			}
		);

		return () => {
			// Need to use `?.` here in case shippingAddressHandler is null.
			shippingAddressUpdateHandler?.removeAllListeners();
		};
	}, [ paymentRequest, paymentRequestType ] );
};

export const useProcessPaymentHandler = (
	stripe,
	paymentRequest,
	paymentRequestType,
	setExpressPaymentError
) => {
	useEffect( () => {
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

		const requestIntentConfirmation = ( intentType, clientSecret ) => {
			const isSetupIntent = intentType === 'si';

			if ( isSetupIntent ) {
				return stripe.handleCardSetup( clientSecret );
			}
			return stripe.handleCardPayment( clientSecret );
		};

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

		const handleIntentConfirmation = ( redirectUrl, intentType ) => (
			confirmation
		) => {
			if ( confirmation.error ) {
				throw confirmation.error;
			}

			const intent = getIntentFromConfirmation(
				confirmation,
				intentType
			);
			if (
				doesIntentRequireCapture( intent ) ||
				didIntentSucceed( intent )
			) {
				window.location = redirectUrl;
			}
		};

		const performPayment = ( evt ) => ( createOrderResponse ) => {
			if ( createOrderResponse.result === 'success' ) {
				evt.complete( 'success' ); // TODO: Is this the right place for this?

				const partials = getRedirectUrlPartials(
					createOrderResponse.redirect
				);

				if ( ! partials || partials.length < 4 ) {
					window.location = createOrderResponse.redirect;
					return;
				}

				const { type, clientSecret, redirectUrl } = partials;

				requestIntentConfirmation( type, clientSecret )
					.then( handleIntentConfirmation( redirectUrl, type ) )
					.catch( ( error ) => {
						setExpressPaymentError( error.message );

						// Report back to the server.
						$.get( redirectUrl + '&is_ajax' );
					} );
			} else {
				evt.complete( 'fail' );
				setExpressPaymentError( createOrderResponse.messages );
			}
		};

		const paymentMethodUpdateHandler = paymentRequest?.on(
			'source',
			( evt ) => {
				const allowPrepaidCards =
					wc_stripe_payment_request_params?.stripe
						?.allow_prepaid_card === 'yes';

				// Check if we allow prepaid cards.
				if (
					! allowPrepaidCards &&
					evt?.source?.card?.funding === 'prepaid'
				) {
					setExpressPaymentError(
						__(
							"Sorry, we're not accepting prepaid cards at this time.",
							'woocommerce-gateway-stripe'
						)
					);
				} else {
					createOrder( evt, paymentRequestType ).then(
						performPayment( evt )
					);
				}
			}
		);

		return () => {
			paymentMethodUpdateHandler?.removeAllListeners();
		};
	}, [ stripe, paymentRequest, paymentRequestType, setExpressPaymentError ] );
};

export const useShippingOptionChangeHandler = (
	paymentRequest,
	paymentRequestType
) => {
	useEffect( () => {
		const shippingOptionChangeHandler = paymentRequest?.on(
			'shippingoptionchange',
			( evt ) => {
				const { shippingOption } = evt;
				updateShippingDetails(
					shippingOption,
					paymentRequestType
				).then( ( response ) => {
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
			}
		);

		return () => {
			shippingOptionChangeHandler?.removeAllListeners();
		};
	}, [ paymentRequest, paymentRequestType ] );
};
