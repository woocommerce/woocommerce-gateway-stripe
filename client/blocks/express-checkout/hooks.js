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
			const getShippingRates = () => {
				// shippingData.shippingRates[ 0 ].shipping_rates will be non-empty
				// only when the express checkout element's default shipping address
				// has a shipping method defined in WooCommerce.
				if (
					shippingData?.shippingRates[ 0 ]?.shipping_rates?.length > 0
				) {
					return shippingData.shippingRates[ 0 ].shipping_rates.map(
						( r ) => {
							return {
								id: r.rate_id,
								amount: parseInt( r.price, 10 ),
								displayName: r.name,
							};
						}
					);
				}

				// Return a default shipping option, as a non-empty shippingRates array
				// is required when shippingAddressRequired is true.
				return [
					getExpressCheckoutData( 'checkout' )
						?.default_shipping_option,
				];
			};

			const options = {
				lineItems: normalizeLineItems( billing?.cartTotalItems ),
				emailRequired: true,
				shippingAddressRequired: shippingData?.needsShipping,
				phoneNumberRequired:
					getExpressCheckoutData( 'checkout' )?.needs_payer_phone ??
					false,
				shippingRates: getShippingRates(),
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
