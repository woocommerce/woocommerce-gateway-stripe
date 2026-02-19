import { StoreNotice } from '@woocommerce/blocks-checkout';
import { CheckoutProvider } from '@stripe/react-stripe-js/checkout';
import React, { useMemo, useState } from 'react';
import CheckoutForm from 'wcstripe/blocks/checkout-sessions/checkout-form';
import { loadStripe } from 'wcstripe/blocks/load-stripe';
import { initializeUPEAppearance } from 'wcstripe/stripe-utils';
import { getFontRulesFromPage } from 'wcstripe/styles/upe';
import { __ } from '@wordpress/i18n';

const stripePromise = loadStripe();

/**
 * Checkout Sessions Container component.
 *
 * @param {Object} props Component props.
 * @return {JSX.Element} The Checkout Sessions Container component.
 */
export const CheckoutSessionsContainer = ( props ) => {
	const { api, testingInstructions } = props;
	const checkoutSessionPromise = useMemo( async () => {
		const response = await api.checkoutSessionsCreateSession();
		const clientSecret = response.data?.client_secret;
		if ( ! clientSecret ) {
			throw new Error(
				__(
					'Unable to initialize a checkout session. Please refresh the page and try again.',
					'woocommerce-gateway-stripe'
				)
			);
		}
		return clientSecret;
	}, [ api ] );
	const [
		paymentProcessorLoadErrorMessage,
		setPaymentProcessorLoadErrorMessage,
	] = useState( null );

	const providerOptions = {
		clientSecret: checkoutSessionPromise,
		adaptivePricing: { allowed: true },
		elementsOptions: {
			appearance: initializeUPEAppearance( api, 'true' ),
			fonts: getFontRulesFromPage(),
		},
	};

	return (
		<>
			{ paymentProcessorLoadErrorMessage?.error?.message && (
				<div className="wc-block-components-notices">
					<StoreNotice status="error" isDismissible={ false }>
						{ paymentProcessorLoadErrorMessage.error.message }
					</StoreNotice>
				</div>
			) }
			<CheckoutProvider
				stripe={ stripePromise }
				options={ providerOptions }
			>
				<CheckoutForm
					onLoadError={ setPaymentProcessorLoadErrorMessage }
					testingInstructions={ testingInstructions }
					{ ...props }
				/>
			</CheckoutProvider>
		</>
	);
};
