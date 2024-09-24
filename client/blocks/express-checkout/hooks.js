import { useCallback } from '@wordpress/element';
import { useStripe, useElements } from '@stripe/react-stripe-js';
import {
	onAbortPaymentHandler,
	onCancelHandler,
	onClickHandler,
	onCompletePaymentHandler,
	onConfirmHandler,
} from 'wcstripe/express-checkout/event-handler';
import {
	getExpressCheckoutButtonStyleSettings,
	getExpressCheckoutData,
	normalizeLineItems,
} from 'wcstripe/express-checkout/utils';

export const useExpressCheckout = ( {
	api,
	billing,
	shippingData,
	onClick,
	onClose,
	setExpressPaymentError,
} ) => {
	const stripe = useStripe();
	const elements = useElements();

	const buttonOptions = getExpressCheckoutButtonStyleSettings();

	const onCancel = () => {
		onCancelHandler();
		onClose();
	};

	const completePayment = ( redirectUrl ) => {
		onCompletePaymentHandler( redirectUrl );
		window.location = redirectUrl;
	};

	const abortPayment = ( onConfirmEvent, message ) => {
		onConfirmEvent.paymentFailed( { reason: 'fail' } );
		setExpressPaymentError( message );
		onAbortPaymentHandler( onConfirmEvent, message );
	};

	const onButtonClick = useCallback(
		( event ) => {
			const options = {
				lineItems: normalizeLineItems( billing?.cartTotalItems ),
				emailRequired: true,
				shippingAddressRequired: shippingData?.needsShipping,
				phoneNumberRequired:
					getExpressCheckoutData( 'checkout' )?.needs_payer_phone ??
					false,
				shippingRates: shippingData?.shippingRates[ 0 ]?.shipping_rates?.map(
					( r ) => {
						return {
							id: r.rate_id,
							amount: parseInt( r.price, 10 ),
							displayName: r.name,
						};
					}
				),
			};

			// Click event from WC Blocks.
			onClick();
			// Global click event handler to ECE.
			onClickHandler( event );
			event.resolve( options );
		},
		[
			onClick,
			billing.cartTotalItems,
			shippingData.needsShipping,
			shippingData.shippingRates,
		]
	);

	const onConfirm = async ( event ) => {
		await onConfirmHandler(
			api,
			stripe,
			elements,
			completePayment,
			abortPayment,
			event
		);
	};

	return {
		buttonOptions,
		onButtonClick,
		onConfirm,
		onCancel,
		elements,
	};
};
