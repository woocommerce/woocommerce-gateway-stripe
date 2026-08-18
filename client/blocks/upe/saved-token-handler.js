import { usePaymentCompleteHandler } from './hooks';
import { useState } from '@wordpress/element';
import { CheckoutContainer } from 'wcstripe/blocks/checkout-sessions/checkout-container';
import SavedTokenCheckoutForm from 'wcstripe/blocks/checkout-sessions/saved-token-checkout-form';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

/**
 * The pre-Adaptive Pricing flow: the server charges a PaymentIntent in the
 * store currency and this component confirms it if necessary.
 *
 * @param {Object} props                                     Payment method interface props.
 * @param {Object} props.api                                 Object containing methods for interacting with Stripe.
 * @param {Object} props.stripe                              The Stripe.js instance.
 * @param {Object} props.elements                            The Stripe Elements instance.
 * @param {Object} props.eventRegistration                   Event registration functions.
 * @param {*}      props.eventRegistration.onCheckoutSuccess The onCheckoutSuccess event.
 * @param {Object} props.emitResponse                        Helpers for usage with observer.
 * @return {JSX.Element} Empty fragment; only registers the handler.
 */
const StoreCurrencySavedTokenHandler = ( {
	api,
	stripe,
	elements,
	eventRegistration: { onCheckoutSuccess },
	emitResponse,
} ) => {
	// Once the server has completed payment processing, confirm the intent of necessary.
	usePaymentCompleteHandler(
		api,
		stripe,
		elements,
		onCheckoutSuccess,
		emitResponse,
		false // No need to save a payment that has already been saved.
	);

	return <></>;
};

export const SavedTokenHandler = ( props ) => {
	const [ shouldFallBack, setShouldFallBack ] = useState( false );
	const blocksConfiguration = getBlocksConfiguration();
	// The server only maps card tokens: single-currency methods (e.g. SEPA)
	// can't settle a converted presentment currency.
	const savedPaymentMethodId =
		blocksConfiguration?.adaptivePricingSavedTokens?.[
			String( props.token )
		] ?? null;
	const stripeSupportsInitCheckout =
		typeof props.api?.getStripe()?.initCheckoutElementsSdk === 'function';

	if (
		shouldFallBack ||
		! blocksConfiguration?.isAdaptivePricingEnabled ||
		! savedPaymentMethodId ||
		! stripeSupportsInitCheckout
	) {
		return <StoreCurrencySavedTokenHandler { ...props } />;
	}

	return (
		<CheckoutContainer
			{ ...props }
			FormComponent={ SavedTokenCheckoutForm }
			savedPaymentMethodId={ savedPaymentMethodId }
			isLoggedIn={ blocksConfiguration?.isLoggedIn }
			isPayerPhoneRequired={ blocksConfiguration?.isPayerPhoneRequired }
			LoadingMask={ props.components?.LoadingMask }
			setPaymentProcessorLoadErrorMessage={ () =>
				setShouldFallBack( true )
			}
			setShouldLoadStripeElements={ setShouldFallBack }
		/>
	);
};
