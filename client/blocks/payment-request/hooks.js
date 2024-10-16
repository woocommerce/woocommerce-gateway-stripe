import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	shippingAddressChangeHandler,
	shippingOptionChangeHandler,
	paymentProcessingHandler,
} from './event-handlers';
import { displayLoginConfirmation } from './login-confirmation';
import {
	getBlocksConfiguration,
	createPaymentRequestUsingCart,
	updatePaymentRequestUsingCart,
} from 'wcstripe/blocks/utils';
import { getCartDetails } from 'wcstripe/api/blocks';

/**
 * This hook takes care of creating a payment request and making sure
 * you can pay through said payment request.
 *
 * @param {Object}  stripe The stripe object used to create the payment request.
 * @param {boolean} needsShipping A value from the Block checkout that indicates whether shipping
 *                                is required or not.
 * @param {Object}  billing - The billing data from the checkout or cart block.
 *
 * @return {Array} An array; first element is the payment request; second element is the payment
 *                 requests type.
 */
export const usePaymentRequest = ( stripe, needsShipping, billing ) => {
	const [ paymentRequest, setPaymentRequest ] = useState( null );
	const [ paymentRequestType, setPaymentRequestType ] = useState( null );
	const [ isUpdatingPaymentRequest, setIsUpdatingPaymentRequest ] = useState(
		false
	);

	// Create a payment request if:
	//   a) Stripe object is loaded; and
	//   b) There is no payment request created already.
	useEffect( () => {
		if ( ! stripe ) {
			return;
		}
		const createPaymentRequest = async () => {
			const cart = await getCartDetails();
			const pr = createPaymentRequestUsingCart( stripe, cart );
			const result = await pr.canMakePayment();

			if ( result ) {
				setPaymentRequest( pr );
				setPaymentRequestType( () => {
					if ( result.applePay ) {
						return 'apple_pay';
					}
					if ( result.googlePay ) {
						return 'google_pay';
					}
					return 'payment_request_api';
				} );
			} else {
				setPaymentRequest( null );
			}
		};
		createPaymentRequest();
	}, [ stripe, needsShipping ] );

	useEffect( () => {
		if ( ! paymentRequest ) {
			return;
		}

		const updatePaymentRequest = async () => {
			setIsUpdatingPaymentRequest( true );
			const cart = await getCartDetails();
			updatePaymentRequestUsingCart( paymentRequest, cart );
			setIsUpdatingPaymentRequest( false );
		};
		updatePaymentRequest();
	}, [
		paymentRequest,
		billing.cartTotal,
		billing.cartTotalItems,
		billing.currency.code,
	] );

	return [ paymentRequest, paymentRequestType, isUpdatingPaymentRequest ];
};

/**
 * Returns an onClick handler for payment request buttons. Checks if login is required, resets
 * the error state, syncs the payment request with the block, and calls the provided click handler.
 *
 * @param {string} paymentRequestType - The payment request type.
 * @param {Function} setExpressPaymentError - Used to set the error state.
 * @param {Function} onClick - The onClick function that should be called on click.
 *
 * @return {Function} An onClick handler for the payment request buttons.
 */
export const useOnClickHandler = (
	paymentRequestType,
	setExpressPaymentError,
	onClick
) => {
	return useCallback(
		( evt, pr ) => {
			// If login is required, display redirect confirmation dialog.
			if ( getBlocksConfiguration()?.login_confirmation ) {
				evt.preventDefault();
				displayLoginConfirmation( paymentRequestType );
				return;
			}

			// Reset any Payment Request errors.
			setExpressPaymentError( '' );

			// Call the Blocks API `onClick` handler.
			onClick();

			// We must manually call payment request `show()` for custom buttons.
			if ( pr ) {
				pr.show();
			}
		},
		[ paymentRequestType, setExpressPaymentError, onClick ]
	);
};

/**
 * Adds a shipping address change event handler to the provided payment request. Updates the
 * order's shipping address when necessary.
 *
 * @param {Object} paymentRequest - The payment request object.
 * @param {string} paymentRequestType - The payment request type.
 */
export const useShippingAddressUpdateHandler = (
	paymentRequest,
	paymentRequestType
) => {
	useEffect( () => {
		const handler = paymentRequest?.on(
			'shippingaddresschange',
			shippingAddressChangeHandler( paymentRequestType )
		);

		return () => {
			// Need to use `?.` here in case paymentRequest is null.
			handler?.removeEventListener( 'shippingaddresschange' );
		};
	}, [ paymentRequest, paymentRequestType ] );
};

/**
 * Adds a shipping option change event handler to the provided payment request.
 *
 * @param {Object} paymentRequest - The payment request object.
 * @param {string} paymentRequestType - The payment request type.
 */
export const useShippingOptionChangeHandler = (
	paymentRequest,
	paymentRequestType
) => {
	useEffect( () => {
		const handler = paymentRequest?.on(
			'shippingoptionchange',
			shippingOptionChangeHandler
		);

		return () => {
			// Need to use `?.` here in case paymentRequest is null.
			handler?.removeEventListener( 'shippingoptionchange' );
		};
	}, [ paymentRequest, paymentRequestType ] );
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
		const handler = paymentRequest?.on(
			'paymentmethod',
			paymentProcessingHandler(
				stripe,
				paymentRequestType,
				setExpressPaymentError
			)
		);

		return () => {
			// Need to use `?.` here in case paymentRequest is null.
			handler?.removeEventListener( 'paymentmethod' );
		};
	}, [ stripe, paymentRequest, paymentRequestType, setExpressPaymentError ] );
};

/**
 * Adds a cancellation handler to the provided payment request.
 *
 * @param {Object} paymentRequest - The payment request object.
 * @param {Function} onClose - A function from the Blocks API.
 */
export const useCancelHandler = ( paymentRequest, onClose ) => {
	useEffect( () => {
		const handler = paymentRequest?.on( 'cancel', onClose );

		return () => {
			// Need to use `?.` here in case paymentRequest is null.
			handler?.removeEventListener( 'cancel' );
		};
	}, [ paymentRequest, onClose ] );
};
