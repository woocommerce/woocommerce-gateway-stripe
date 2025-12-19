import { CheckoutProvider } from '@stripe/react-stripe-js/checkout';
import React, { useMemo } from 'react';
import CheckoutForm from 'wcstripe/blocks/checkout-sessions/checkout-form';
import { loadStripe } from 'wcstripe/blocks/load-stripe';
import { useState } from '@wordpress/element';

const stripePromise = loadStripe();

export const CheckoutSessionsContainer = ( props ) => {
	const { api } = props;
	const promise = useMemo( () => {
		return api
			.checkoutSessionsCreateSession()
			.then( ( r ) => r.client_secret );
	}, [ api ] );
	const [
		paymentProcessorLoadErrorMessage,
		setPaymentProcessorLoadErrorMessage,
	] = useState( null );

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
				options={ { clientSecret: promise } }
			>
				<CheckoutForm
					api={ api }
					onLoadError={ setPaymentProcessorLoadErrorMessage }
					{ ...props }
				/>
			</CheckoutProvider>
		</>
	);
};
