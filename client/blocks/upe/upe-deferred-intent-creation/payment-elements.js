/**
 * External dependencies
 */
import { StoreNotice } from '@woocommerce/blocks-checkout';
import {
	CheckoutProvider,
	useCheckout,
} from '@stripe/react-stripe-js/checkout';
import { PaymentElement } from '@stripe/react-stripe-js';
import { useMemo } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { loadStripe } from 'wcstripe/blocks/load-stripe';
/**
 * Internal dependencies
 */
import WCStripeAPI from 'wcstripe/api';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

const stripe = loadStripe();

const CheckoutForm = () => {
	const checkoutState = useCheckout();

	if ( checkoutState.type === 'loading' ) {
		return <div>Loading...</div>;
	} else if ( checkoutState.type === 'error' ) {
		return <div>Error: { checkoutState.error.message }</div>;
	}
	return <PaymentElement />;
};

/**
 * Renders a Stripe Payment elements component.
 *
 * @param {*}           props                 Additional props for payment processing.
 * @param {WCStripeAPI} props.api             Object containing methods for interacting with Stripe.
 * @param {string}      props.paymentMethodId The ID of the payment method.
 *
 * @return {JSX.Element} Rendered Payment elements.
 */
const PaymentElements = ( { api, paymentMethodId } ) => {
	const [ paymentProcessorLoadErrorMessage ] = useState( null );

	const promise = useMemo( () => {
		return api
			.createCheckoutSession(
				getBlocksConfiguration()?.orderId,
				paymentMethodId
			)
			.then( ( r ) => {
				console.log('r', r.client_secret);
				return r.client_secret;
			} );
	}, [ api, paymentMethodId ] );

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
				stripe={ stripe }
				options={ { clientSecret: promise } }
			>
				<CheckoutForm />
			</CheckoutProvider>
		</>
	);
};

/**
 * Renders a Stripe Payment elements component.
 *
 * @param {string}      paymentMethodId
 * @param {Array}       upeMethods
 * @param {WCStripeAPI} api
 * @param {string}      description
 * @param {string}      testingInstructions
 * @param {boolean}     showSaveOption
 * @param {boolean}     supportsDeferredIntent
 *
 * @return {JSX.Element} Rendered Payment elements.
 */
export const getDeferredIntentCreationUPEFields = (
	paymentMethodId,
	upeMethods,
	api,
	description,
	testingInstructions,
	showSaveOption,
	supportsDeferredIntent
) => {
	return (
		<PaymentElements
			paymentMethodId={ paymentMethodId }
			upeMethods={ upeMethods }
			api={ api }
			description={ description }
			testingInstructions={ testingInstructions }
			showSaveOption={ showSaveOption }
			supportsDeferredIntent={ supportsDeferredIntent }
		/>
	);
};
