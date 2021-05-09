/**
 * External dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getCartDetails } from '../../api';
import {
	shippingAddressChangeHandler,
	shippingOptionChangeHandler,
	paymentProcessingHandler,
} from './event-handlers';
import {
	createPaymentRequestUsingCart,
	updatePaymentRequest,
} from '../stripe-utils';

/**
 * This hook takes care of creating a payment request and making sure
 * you can pay through said payment request.
 *
 * @param {Object}  stripe The stripe object used to create the payment request.
 * @param {boolean} needsShipping A value from the Block checkout that indicates whether shipping
 *                                is required or not.
 *
 * @return {Array} An array; first element is the payment request; second element is the payment
 *                 requests type.
 */
export const usePaymentRequest = ( stripe, needsShipping ) => {
	const [ paymentRequest, setPaymentRequest ] = useState( null );
	const [ paymentRequestType, setPaymentRequestType ] = useState( null );

	// Create a payment request if:
	//   a) Stripe object is loaded; and
	//   b) There is no payment request created already.
	useEffect( () => {
		// Do nothing if Stripe object isn't loaded or paymentRequest already exists.
		if ( ! stripe || paymentRequest ) {
			return;
		}

		getCartDetails().then( ( cart ) => {
			const pr = createPaymentRequestUsingCart( stripe, cart );

			pr.canMakePayment().then( ( result ) => {
				if ( result ) {
					setPaymentRequest( pr );
					if ( result.applePay ) {
						setPaymentRequestType( 'apple_pay' );
					} else if ( result.googlePay ) {
						setPaymentRequestType( 'google_pay' );
					} else {
						setPaymentRequestType( 'payment_request_api' );
					}
				}
			} );
		} );
	}, [ paymentRequest, stripe ] );

	// Reset the payment request if the need for shipping changes.
	useEffect( () => {
		setPaymentRequest( null );
	}, [ needsShipping ] );

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
			shippingAddressChangeHandler( paymentRequestType )
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
		const sippingOptionHandler = paymentRequest?.on(
			'shippingoptionchange',
			shippingOptionChangeHandler
		);

		return () => {
			// Need to use `?.` here in case shippingAddressHandler is null.
			sippingOptionHandler?.removeAllListeners();
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
		const paymentMethodUpdateHandler = paymentRequest?.on(
			'source',
			paymentProcessingHandler(
				stripe,
				paymentRequestType,
				setExpressPaymentError
			)
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
