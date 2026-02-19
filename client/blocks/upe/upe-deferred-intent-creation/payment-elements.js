/**
 * External dependencies
 */
import { StoreNotice } from '@woocommerce/blocks-checkout';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
/**
 * Internal dependencies
 */
import WCStripeAPI from 'wcstripe/api';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { CheckoutContainer } from 'wcstripe/blocks/checkout-sessions/checkout-container';
import { ElementsContainer } from 'wcstripe/blocks/payment-intents/elements-container';

const stripeServerData = getBlocksConfiguration();

/**
 * Renders a Stripe Payment elements component.
 *
 * @param {*}           props                        Additional props for payment processing.
 * @param {WCStripeAPI} props.api                    Object containing methods for interacting with Stripe.
 * @param {string}      props.paymentMethodId        The ID of the payment method.
 * @param {boolean}     props.supportsDeferredIntent Whether the payment method supports deferred intent creation.
 * @param {Object}      props.components             Object containing components for rendering.
 *
 * @return {JSX.Element} Rendered Payment elements.
 */
export const PaymentElements = ( {
	api,
	paymentMethodId,
	supportsDeferredIntent,
	components: { LoadingMask },
	...props
} ) => {
	const [ , setClientSecret ] = useState( null );
	const [ paymentIntentId, setPaymentIntentId ] = useState( null );
	const [ hasRequestedIntent, setHasRequestedIntent ] = useState( false );
	const [ errorMessage, setErrorMessage ] = useState( null );
	const [ paymentProcessorLoadErrorMessage ] = useState( null );
	const [ shouldLoadStripeElements, setShouldLoadStripeElements ] = useState(
		! stripeServerData?.isAdaptivePricingEnabled
	);

	const isAdaptivePricingSupported =
		stripeServerData?.isAdaptivePricingEnabled;

	useEffect( () => {
		if ( supportsDeferredIntent || hasRequestedIntent ) {
			return;
		}

		async function createIntent() {
			try {
				const paymentNeeded = stripeServerData?.isPaymentNeeded;
				const response = paymentNeeded
					? await api.createIntent(
							stripeServerData?.orderId,
							paymentMethodId
					  )
					: await api.initSetupIntent( paymentMethodId );

				setClientSecret( response.client_secret );
				setPaymentIntentId( response.id );
			} catch ( error ) {
				const paymentMethodTitle =
					stripeServerData?.paymentMethodsConfig?.[ paymentMethodId ]
						?.title ?? '';
				setErrorMessage(
					error?.message ??
						sprintf(
							// translators: %s is the payment method title.
							__(
								'Failed to load %s payment method. Please refresh the page and try again.',
								'woocommerce-gateway-stripe'
							),
							paymentMethodTitle
						)
				);
			}
		}

		setHasRequestedIntent( true );
		createIntent();
	}, [
		api,
		hasRequestedIntent,
		paymentIntentId,
		paymentMethodId,
		supportsDeferredIntent,
	] );

	if ( errorMessage ) {
		return (
			<div className="wc-block-components-notices">
				<StoreNotice status="error" isDismissible={ false }>
					{ errorMessage }
				</StoreNotice>
			</div>
		);
	}

	let containerComponent;
	if ( isAdaptivePricingSupported && ! shouldLoadStripeElements ) {
		containerComponent = (
			<CheckoutContainer
				api={ api }
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
				LoadingMask={ LoadingMask }
				{ ...props }
			/>
		);
	} else {
		containerComponent = (
			<ElementsContainer
				api={ api }
				LoadingMask={ LoadingMask }
				{ ...props }
			/>
		);
	}

	return (
		<>
			{ paymentProcessorLoadErrorMessage?.error?.message && (
				<div className="wc-block-components-notices">
					<StoreNotice status="error" isDismissible={ false }>
						{ paymentProcessorLoadErrorMessage.error.message }
					</StoreNotice>
				</div>
			) }
			{ containerComponent }
		</>
	);
};
