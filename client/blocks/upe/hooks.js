import { useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import confirmCardPayment from './confirm-card-payment.js';
import { WC_STORE_CART } from 'wcstripe/blocks/credit-card/constants';

/**
 * Handles the Block Checkout onCheckoutSuccess event.
 *
 * Confirms the payment intent which was created on server and is now ready to be confirmed. The intent ID is passed in the paymentDetails object via the
 * redirect arg which will be in the following format: #wc-stripe-confirm-pi/si:{order_id}:{client_secret}:{nonce}
 *
 * @param {*} api               The api object.
 * @param {*} stripe            The Stripe object.
 * @param {*} elements          The Stripe elements object.
 * @param {*} onCheckoutSuccess The onCheckoutSuccess event.
 * @param {*} emitResponse      Various helpers for usage with observer.
 * @param {*} shouldSavePayment Whether or not to save the payment method.
 */
export const usePaymentCompleteHandler = (
	api,
	stripe,
	elements,
	onCheckoutSuccess,
	emitResponse,
	shouldSavePayment
) => {
	// Once the server has completed payment processing, confirm the intent of necessary.
	useEffect(
		() =>
			onCheckoutSuccess( ( { processingResponse: { paymentDetails } } ) =>
				confirmCardPayment(
					api,
					paymentDetails,
					emitResponse,
					shouldSavePayment
				)
			),
		// not sure if we need to disable this, but kept it as-is to ensure nothing breaks. Please consider passing all the deps.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ elements, stripe, api, shouldSavePayment ]
	);
};

/**
 * Handles the Block Checkout onCheckoutFail event.
 *
 * Displays the error message returned from server in the paymentDetails object in the PAYMENTS notice context container.
 *
 * @param {*} api            The api object.
 * @param {*} stripe         The Stripe object.
 * @param {*} elements       The Stripe elements object.
 * @param {*} onCheckoutFail The onCheckoutFail event.
 * @param {*} emitResponse   Various helpers for usage with observer.
 */
export const usePaymentFailHandler = (
	api,
	stripe,
	elements,
	onCheckoutFail,
	emitResponse
) => {
	useEffect(
		() =>
			onCheckoutFail( ( { processingResponse: { paymentDetails } } ) => {
				return {
					type: 'failure',
					message: paymentDetails.errorMessage,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			} ),
		[
			elements,
			stripe,
			api,
			onCheckoutFail,
			emitResponse.noticeContexts.PAYMENTS,
		]
	);
};

/**
 * Returns the customer data and setters for the customer data.
 *
 * @return {Object} An object containing the customer data.
 */
export const useCustomerData = () => {
	const { customerData, isInitialized } = useSelect( ( select ) => {
		const store = select( WC_STORE_CART );
		return {
			customerData: store.getCustomerData(),
			isInitialized: store.hasFinishedResolution( 'getCartData' ),
		};
	} );
	const {
		setShippingAddress,
		setBillingAddress,
		setBillingData,
	} = useDispatch( WC_STORE_CART );

	let customerBillingAddress = customerData.billingData;
	let setCustomerBillingAddress = setBillingData;

	//added for backwards compatibility -> billingData was renamed to billingAddress
	if ( customerData.billingData === undefined ) {
		customerBillingAddress = customerData.billingAddress;
		setCustomerBillingAddress = setBillingAddress;
	}

	return {
		isInitialized,
		billingAddress: customerBillingAddress,
		shippingAddress: customerData.shippingAddress,
		setBillingAddress: setCustomerBillingAddress,
		setShippingAddress,
	};
};
