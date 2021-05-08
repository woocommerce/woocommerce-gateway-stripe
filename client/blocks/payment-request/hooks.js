/* global wc_stripe_payment_request_params */

/**
 * External dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
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
import { updatePaymentRequest } from '../stripe-utils';

/**
 * This hook takes care of creating a payment request and making sure
 * you can pay through said payment request.
 *
 * @param {Object} stripe The stripe object used to create the payment request.
 *
 * @return {Array} An array; first element is the payment request; second element is the payment
 *                 requests type.
 */
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

/**
 * Adds a shipping address change event handler to the provided payment request. Updates the
 * order's shipping address when necessary.
 *
 * @param {Object} paymentRequest - The payment request object.
 * @param {string} paymentRequestType - The payment request type.
 * @param {Function} setShippingAddress - Used to set the shippingaddress in the Block.
 */
export const useShippingAddressUpdateHandler = (
	paymentRequest,
	paymentRequestType,
	setShippingAddress
) => {
	useEffect( () => {
		// Need to use `?.` here in case paymentRequest is null.
		const shippingAddressUpdateHandler = paymentRequest?.on(
			'shippingaddresschange',
			( evt ) => {
				const { shippingAddress } = evt;

				// Update the payment request shipping information address.
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
			// Need to use `?.` here in case shippingAddressUpdateHandler is null.
			shippingAddressUpdateHandler?.removeAllListeners();
		};
	}, [ paymentRequest, paymentRequestType, setShippingAddress ] );
};

/**
 * Adds a shipping option change event handler to the provided payment request.
 *
 * @param {Object} paymentRequest - The payment request object.
 * @param {string} paymentRequestType - The payment request type.
 * @param {Function} setSelectedRates - A function used to set the selected shipping method in the
 *                                      Block.
 */
export const useShippingOptionChangeHandler = (
	paymentRequest,
	paymentRequestType,
	setSelectedRates
) => {
	useEffect( () => {
		// Need to use `?.` here in case paymentRequest is null.
		const shippingOptionChangeHandler = paymentRequest?.on(
			'shippingoptionchange',
			( evt ) => {
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
			}
		);

		return () => {
			// Need to use `?.` here in case shippingAddressHandler is null.
			shippingOptionChangeHandler?.removeAllListeners();
		};
	}, [ paymentRequest, paymentRequestType, setSelectedRates ] );
};

/**
 * Adds a payment event handler to the provided payment request.
 *
 * @param {Object} stripe - The stripe object used to confirm and create a payment intent.
 * @param {Object} paymentRequest - The payment request object.
 * @param {string} paymentRequestType - The payment request type.
 * @param {Function} setExpressPaymentError - A function used to expose an error message to show
 *                                            the customer.
 */
export const useProcessPaymentHandler = (
	stripe,
	paymentRequest,
	paymentRequestType,
	setExpressPaymentError
) => {
	useEffect( () => {
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
		 * @param {string} intentType - The type of intent. Either `pi` or `si`.
		 * @param {string} clientSecret - Client secret returned from Stripe.
		 *
		 * @return {Promise} A promise from Stripe with the confirmed intent or an error.
		 */
		const requestIntentConfirmation = ( intentType, clientSecret ) => {
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

			const intent = getIntentFromConfirmation(
				confirmation,
				intentType
			);
			if (
				doesIntentRequireCapture( intent ) ||
				didIntentSucceed( intent )
			) {
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
		 * @param {Object} evt - The `source` event from the Stripe payment request button.
		 */
		const performPayment = ( evt ) => ( createOrderResponse ) => {
			if ( createOrderResponse.result === 'success' ) {
				evt.complete( 'success' );

				const partials = getRedirectUrlPartials(
					createOrderResponse.redirect
				);

				// If no information is embedded in the URL that means the payment doesn't need
				// verification and we can proceed as usual.
				if ( ! partials || partials.length < 4 ) {
					window.location = createOrderResponse.redirect;
					return;
				}

				const { type, clientSecret, redirectUrl } = partials;

				// The payment requires 3DS verification, so we try to take care of that here.
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
					// Create the order and attempt to pay.
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

/**
 * Returns an onClick handler for payment request buttons. Resets the error state, syncs the
 * payment request with the block, and calls the provided click handler.
 *
 * @param {Object}   paymentRequest - The Payment Request object.
 * @param {Function} setExpressPaymentError - Used to set the error state.
 * @param {Function} onClick - The onClick function that should be called on click.
 * @param {Object}   billing - The billing data from the checkout or cart block.
 *
 * @return {Function} An onClick handler for the payment request buttons.
 */
export const useOnClickHandler = (
	paymentRequest,
	setExpressPaymentError,
	onClick,
	billing
) => {
	return useCallback( () => {
		// Reset any Payment Request errors.
		setExpressPaymentError( '' );

		// Update the payment request with new billing information.
		if ( paymentRequest ) {
			updatePaymentRequest( {
				paymentRequest,
				total: billing.cartTotal,
				currencyCode: billing.currency.code.toLowerCase(),
				cartTotalItems: billing.cartTotalItems,
			} );
		}

		// Call the Blocks API `onClick` handler.
		onClick();
	}, [ paymentRequest, setExpressPaymentError, onClick, billing ] );
};

/**
 * Adds a cancellation handler to the provided payment request.
 *
 * @param {Object} paymentRequest - The payment request object.
 * @param {Function} onClose - A function from the Blocks API.
 */
export const useCancelHandler = ( paymentRequest, onClose ) => {
	useEffect( () => {
		paymentRequest?.on( 'cancel', () => {
			onClose();
		} );
	}, [ paymentRequest, onClose ] );
};
