/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { StoreNotice } from '@woocommerce/blocks-checkout';
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
 * @param {*}           props                        Additional props for payment processing.
 * @param {WCStripeAPI} props.api                    Object containing methods for interacting with Stripe.
 * @param {string}      props.paymentMethodId        The ID of the payment method.
 * @param {boolean}     props.supportsDeferredIntent Whether the payment method supports deferred intent creation.
 * @param {Object}      props.components             Object containing components for rendering.
 *
 * @return {JSX.Element} Rendered Payment elements.
 */
const PaymentElements = ( {
	api,
	paymentMethodId,
	supportsDeferredIntent,
	components: { LoadingMask },
	...props
} ) => {
	const [ clientSecret, setClientSecret ] = useState( null );
	const [ paymentIntentId, setPaymentIntentId ] = useState( null );
	const [ hasRequestedIntent, setHasRequestedIntent ] = useState( false );

	const [ errorMessage, setErrorMessage ] = useState( null );
	const [
		paymentProcessorLoadErrorMessage,
		setPaymentProcessorLoadErrorMessage,
	] = useState( null );

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
				const paymentMethodTitle =
					getBlocksConfiguration()?.paymentMethodsConfig?.[
						paymentMethodId
					]?.title ?? '';
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

	// Build options object.
	let options = {
		appearance: initializeUPEAppearance( api, 'true' ),
		paymentMethodCreation: 'manual',
		fonts: getFontRulesFromPage(),
	};

	if ( supportsDeferredIntent ) {
		options = {
			...options,
			...{
				mode: amount < 1 ? 'setup' : 'payment',
				amount,
				currency: getBlocksConfiguration()?.currency.toLowerCase(),
			},
		};

		if ( getBlocksConfiguration()?.isSPEEnabled ) {
			options.appearance.rules = {
				...options.appearance?.rules,
				...{
					'.RadioIcon': {
						width: '2.1em',
					},
					'.RadioIconOuter': {
						strokeWidth: '2px',
					},
				},
			};
			options = {
				...options,
				...{
					paymentMethodConfiguration: 'pmc_...',
				},
			};
		} else {
			options = {
				...options,
				...{
					paymentMethodTypes: getPaymentMethodTypes(
						paymentMethodId
					),
				},
			};
		}

		// If the cart contains a subscription or the payment method supports saving, we need to use off_session setup so Stripe can display appropriate terms and conditions.
		if (
			getBlocksConfiguration()?.cartContainsSubscription ||
			props.showSaveOption
		) {
			options = {
				...options,
				...{
					setupFutureUsage: 'off_session',
				},
			};
		}
	} else {
		options = {
			...options,
			...{ clientSecret },
		};
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
			<Elements stripe={ stripe } options={ options }>
				<PaymentProcessor
					api={ api }
					paymentIntentId={ paymentIntentId }
					paymentMethodId={ paymentMethodId }
					onLoadError={ setPaymentProcessorLoadErrorMessage }
					{ ...props }
				/>
			</Elements>
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
