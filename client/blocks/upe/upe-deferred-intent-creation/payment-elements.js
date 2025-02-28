/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Elements } from '@stripe/react-stripe-js';
/**
 * Internal dependencies
 */
import {
	getPaymentMethodTypes,
	initializeUPEAppearance,
} from 'wcstripe/stripe-utils';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import { getFontRulesFromPage } from 'wcstripe/styles/upe';
import WCStripeAPI from 'wcstripe/api';

/**
 * Renders a Stripe Payment elements component.
 *
 * @param {*}           props                        Additional props for payment processing.
 * @param {WCStripeAPI} props.api                    Object containing methods for interacting with Stripe.
 * @param {Object}      props.components             Object containing components for rendering.
 * @param {string}      props.paymentMethodId        The ID of the payment method.
 * @param {boolean}     props.showSaveOption         Whether to show the save payment option.
 * @param {boolean}     props.supportsDeferredIntent Whether the payment method supports deferred intent creation.
 * @param {JSX.Element} props.children               Child elements to render.
 *
 * @return {JSX.Element} Rendered Payment elements.
 */
export const PaymentElements = ( {
	api,
	components: { LoadingMask },
	paymentMethodId,
	showSaveOption,
	supportsDeferredIntent,
	children,
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
					paymentMethodId
				);

				setClientSecret( response.client_secret );
				setPaymentIntentId( response.id );
			} catch ( error ) {
				// TODO: Gracefully handle errors.
				// https://github.com/woocommerce/woocommerce-gateway-stripe/issues/3830
				console.log( 'error', error ); // eslint-disable-line no-console
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
						paymentMethodId
					),
			  }
			: { clientSecret } ),
		// If the cart contains a subscription or the payment method supports saving, we need to use off_session setup so Stripe can display appropriate terms and conditions.
		...( supportsDeferredIntent &&
			( getBlocksConfiguration()?.cartContainsSubscription ||
				showSaveOption ) && {
				setupFutureUsage: 'off_session',
			} ),
	};

	return (
		<Elements stripe={ stripe } options={ options }>
			{ children }
		</Elements>
	);
};
