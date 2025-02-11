/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Elements } from '@stripe/react-stripe-js';
/**
 * Internal dependencies
 */
import PaymentProcessor from './payment-processor';
import WCStripeAPI from 'wcstripe/api';
import {
	getPaymentMethodTypes,
	initializeUPEAppearance,
} from 'wcstripe/stripe-utils';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { getFontRulesFromPage } from 'wcstripe/styles/upe';

/**
 * Renders a Stripe Payment elements component.
 *
 * @param {*}           props                 Additional props for payment processing.
 * @param {WCStripeAPI} props.api             Object containing methods for interacting with Stripe.
 * @param {string}      props.paymentMethodId The ID of the payment method.
 *
 * @return {JSX.Element} Rendered Payment elements.
 */
const PaymentElements = ( {
	api,
	supportsDeferredIntent,
	components: { LoadingMask },
	...props
} ) => {
	const [ clientSecret, setClientSecret ] = useState( null );
	const [ paymentIntentId, setPaymentIntentId ] = useState( null );
	const [ hasRequestedIntent, setHasRequestedIntent ] = useState( false );

	useEffect( () => {
		if ( supportsDeferredIntent || hasRequestedIntent ) {
			return;
		}

		async function createIntent() {
			try {
				const response = await api.createIntent(
					getBlocksConfiguration()?.orderId,
					props.paymentMethodId
				);

				setClientSecret( response.client_secret );
				setPaymentIntentId( response.id );
			} catch ( error ) {
				// TODO: Gracefully handle errors.
				console.log( 'error', error ); // eslint-disable-line no-console
			}
		}

		setHasRequestedIntent( true );
		createIntent();
	}, [
		api,
		hasRequestedIntent,
		paymentIntentId,
		props.paymentMethodId,
		supportsDeferredIntent,
	] );

	// If a client secret is required, wait until it is available.
	if ( ! supportsDeferredIntent && ! clientSecret ) {
		return (
			<LoadingMask
				isLoading={ true }
				showSpinner={ true }
				screenReaderLabel={ __(
					'Loading payment method…',
					'woocommerce-gateway-stripe'
				) }
			/>
		);
	}

	const stripe = api.getStripe();
	const amount = Number( getBlocksConfiguration()?.cartTotal );
	const currency = getBlocksConfiguration()?.currency.toLowerCase();
	const appearance = initializeUPEAppearance( api, 'true' );

	// Build options object.
	const options = {
		appearance,
		paymentMethodCreation: 'manual',
		fonts: getFontRulesFromPage(),
		...( supportsDeferredIntent
			? {
					mode: amount < 1 ? 'setup' : 'payment',
					amount,
					currency,
					paymentMethodTypes: getPaymentMethodTypes(
						props.paymentMethodId
					),
			  }
			: { clientSecret } ),
		// If the cart contains a subscription or the payment method supports saving, we need to use off_session setup so Stripe can display appropriate terms and conditions.
		...( supportsDeferredIntent &&
			( getBlocksConfiguration()?.cartContainsSubscription ||
				props.showSaveOption ) && {
				setupFutureUsage: 'off_session',
			} ),
	};

	return (
		<Elements stripe={ stripe } options={ options }>
			<PaymentProcessor
				api={ api }
				paymentIntentId={ paymentIntentId }
				{ ...props }
			/>
		</Elements>
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
