import { useStripe, useElements } from '@stripe/react-stripe-js';
import { useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	onAbortPaymentHandler,
	onCancelHandler,
	onClickHandler,
	onCompletePaymentHandler,
	onConfirmHandler,
} from 'wcstripe/express-checkout/event-handler';
import {
	displayExpressCheckoutNotice,
	getExpressCheckoutButtonStyleSettings,
	getExpressCheckoutData,
	normalizeLineItems,
} from 'wcstripe/express-checkout/utils';
import { transformPriceWithMinorUnits } from 'wcstripe/express-checkout/transformers/wc-to-stripe';
import 'wcstripe/express-checkout/compatibility/wc-order-attribution';
import 'wcstripe/express-checkout/compatibility/wc-product-page';

export const useExpressCheckout = ( {
	api,
	billing,
	shippingData,
	onClick,
	onClose,
	setExpressPaymentError,
	expressPaymentMethod,
} ) => {
	const stripe = useStripe();
	const elements = useElements();

	const buttonOptions = useMemo(
		() => getExpressCheckoutButtonStyleSettings( expressPaymentMethod ),
		[ expressPaymentMethod ]
	);
	const transformAmountForStripe = useCallback(
		( amount ) =>
			transformPriceWithMinorUnits( amount, billing.currency.minorUnit ),
		[ billing.currency.minorUnit ]
	);
	const parseAndTransformAmount = useCallback(
		( amount ) => transformAmountForStripe( parseInt( amount, 10 ) ),
		[ transformAmountForStripe ]
	);

	const onCancel = useCallback( () => {
		onCancelHandler();
		onClose();
	}, [ onClose ] );

	const completePayment = useCallback( ( redirectUrl ) => {
		onCompletePaymentHandler( redirectUrl );
		window.location = redirectUrl;
	}, [] );

	const abortPayment = useCallback(
		( onConfirmEvent, message ) => {
			// If we have a multiline message using newlines, replace them with <br>.
			const formattedMessage = message.replace( /\n/g, '<br>' );
			setExpressPaymentError( formattedMessage );

			onAbortPaymentHandler( onConfirmEvent, message );

			// The wallet sheet only closes once the confirm event gets a terminal
			// result, so order errors must fail it too. A late call rejects an
			// internal Stripe promise asynchronously, after the message is shown.
			onConfirmEvent.paymentFailed( { reason: 'fail' } );
		},
		[ setExpressPaymentError ]
	);

	const onButtonClick = useCallback(
		async ( event ) => {
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
								amount: parseAndTransformAmount( r.price ),
								displayName: r.name,
							};
						}
					);
				}

				// Return a default shipping option, as a non-empty shippingRates array
				// is required when shippingAddressRequired is true.
				const defaultShippingOption =
					getExpressCheckoutData(
						'checkout'
					)?.default_shipping_option;
				return defaultShippingOption ? [ defaultShippingOption ] : [];
			};

			const lineItems = normalizeLineItems( billing.cartTotalItems ).map(
				( lineItem ) => ( {
					...lineItem,
					amount: parseAndTransformAmount( lineItem.amount ),
				} )
			);
			const transformedTotalAmount = transformAmountForStripe(
				billing.cartTotal.value
			);
			const totalAmountOfLineItems = lineItems.reduce(
				( acc, lineItem ) => {
					return acc + lineItem.amount;
				},
				0
			);

			const options = {
				// if the `billing.cartTotal.value` is less than the total of `lineItems`, Stripe throws an error
				// it can sometimes happen that the total is _slightly_ less, due to rounding errors on individual items/taxes/shipping
				// (or with the `woocommerce_tax_round_at_subtotal` setting).
				// if that happens, let's just not return any of the line items.
				// This way, just the total amount will be displayed to the customer.
				lineItems:
					transformedTotalAmount < totalAmountOfLineItems
						? []
						: lineItems,
				emailRequired: true,
				shippingAddressRequired: shippingData?.needsShipping,
				phoneNumberRequired:
					getExpressCheckoutData( 'checkout' )?.needs_payer_phone ??
					false,
				...( shippingData?.needsShipping && {
					shippingRates: getShippingRates(),
				} ),
			};

			// Click event from WC Blocks.
			onClick();

			if ( getExpressCheckoutData( 'taxes_based_on_billing' ) ) {
				displayExpressCheckoutNotice(
					__(
						'Final taxes charged can differ based on your actual billing address when using Express Checkout buttons (Link, Google Pay or Apple Pay).',
						'woocommerce-gateway-stripe'
					),
					'info',
					[ 'ece-taxes-info' ]
				);
			}

			// Global click event handler to ECE.
			onClickHandler( event );
			event.resolve( options );
		},
		[
			onClick,
			billing.cartTotalItems,
			billing.cartTotal.value,
			parseAndTransformAmount,
			transformAmountForStripe,
			shippingData.needsShipping,
			shippingData.shippingRates,
		]
	);

	const onConfirm = useCallback(
		async ( event ) => {
			return await onConfirmHandler( {
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event,
			} );
		},
		[ api, stripe, elements, completePayment, abortPayment ]
	);

	return {
		buttonOptions,
		onButtonClick,
		onConfirm,
		onCancel,
		elements,
	};
};
