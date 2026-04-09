/**
 * External dependencies
 */
import { StoreNotice } from '@woocommerce/blocks-checkout';
import { Elements } from '@stripe/react-stripe-js';
import PaymentProcessor from './payment-processor';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
/**
 * Internal dependencies
 */
import WCStripeAPI from 'wcstripe/api';
import {
	getPaymentMethodTypes,
	initializeUPEAppearance,
	getExcludedPaymentMethodTypes,
} from 'wcstripe/stripe-utils';
import {
	getBlocksConfiguration,
	shouldSetupOffSessionPayment,
} from 'wcstripe/blocks/utils';
import { getFontRulesFromPage } from 'wcstripe/styles/upe';
import { CheckoutContainer } from 'wcstripe/blocks/checkout-sessions/checkout-container';

/**
 * Renders a Stripe Elements component for payment processing.
 *
 * TODO: Move to a new `payment-intents` folder.
 *
 * @param {Object} props Component props.
 * @return {JSX.Element} The Stripe Elements component.
 */
const ElementsContainer = ( props ) => {
	const {
		api,
		LoadingMask,
		paymentMethodId,
		setErrorMessage,
		setPaymentProcessorLoadErrorMessage,
		supportsDeferredIntent,
	} = props;
	const stripeServerData = getBlocksConfiguration();
	const paymentMethodsConfig = stripeServerData?.paymentMethodsConfig;

	const [ clientSecret, setClientSecret ] = useState( null );
	const [ paymentIntentId, setPaymentIntentId ] = useState( null );
	const [ hasRequestedIntent, setHasRequestedIntent ] = useState( false );

	useEffect( () => {
		if ( supportsDeferredIntent || hasRequestedIntent ) {
			return;
		}

		/**
		 * Creates a payment or setup intent depending on whether payment is needed, and sets the client secret and payment intent ID in state.
		 *
		 * @return {Promise<void>}
		 */
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
		setErrorMessage,
		stripeServerData,
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

	// Build options object.
	let options = {
		appearance: initializeUPEAppearance( 'true' ),
		paymentMethodCreation: 'manual',
		fonts: getFontRulesFromPage(),
	};

	const stripe = api.getStripe();
	const amount = Number( stripeServerData?.cartTotal );

	if ( supportsDeferredIntent ) {
		options = {
			...options,
			...{
				mode: amount < 1 ? 'setup' : 'payment',
				amount,
				currency: stripeServerData?.currency.toLowerCase(),
			},
		};

		if ( stripeServerData?.shouldShowOptimizedCheckout ) {
			options = {
				...options,
				...{
					paymentMethodConfiguration:
						stripeServerData?.paymentMethodConfigurationId,
					// Exclude unsupported payment methods - calculated dynamically on server side
					excludedPaymentMethodTypes: getExcludedPaymentMethodTypes(),
				},
			};
		} else {
			options = {
				...options,
				...{
					paymentMethodTypes:
						getPaymentMethodTypes( paymentMethodId ),
				},
			};

			// If the cart contains a auto-renewing subscription or the payment method supports saving, we need to use off_session setup so Stripe can display appropriate terms and conditions.
			if (
				shouldSetupOffSessionPayment(
					props.showSaveOption,
					paymentMethodsConfig[ paymentMethodId ].isReusable
				)
			) {
				options = {
					...options,
					...{
						setupFutureUsage: 'off_session',
					},
				};
			}
		}
	} else {
		options = {
			...options,
			...{ clientSecret },
		};
	}

	return (
		<Elements stripe={ stripe } options={ options }>
			<PaymentProcessor
				api={ api }
				paymentIntentId={ paymentIntentId }
				paymentMethodId={ paymentMethodId }
				onLoadError={ setPaymentProcessorLoadErrorMessage }
				{ ...props }
			/>
		</Elements>
	);
};

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
	const stripeServerData = getBlocksConfiguration();
	const isAdaptivePricingSupported =
		stripeServerData?.isAdaptivePricingEnabled;

	const [ errorMessage, setErrorMessage ] = useState( null );
	const [
		paymentProcessorLoadErrorMessage,
		setPaymentProcessorLoadErrorMessage,
	] = useState( null );
	const [ shouldLoadStripeElements, setShouldLoadStripeElements ] = useState(
		! stripeServerData?.isAdaptivePricingEnabled
	);

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
				isLoggedIn={ stripeServerData?.isLoggedIn }
				isPayerPhoneRequired={ stripeServerData?.isPayerPhoneRequired }
				setPaymentProcessorLoadErrorMessage={
					setPaymentProcessorLoadErrorMessage
				}
				setShouldLoadStripeElements={ setShouldLoadStripeElements }
				LoadingMask={ LoadingMask }
				{ ...props }
			/>
		);
	} else {
		containerComponent = (
			<ElementsContainer
				api={ api }
				paymentMethodId={ paymentMethodId }
				setErrorMessage={ setErrorMessage }
				setPaymentProcessorLoadErrorMessage={
					setPaymentProcessorLoadErrorMessage
				}
				supportsDeferredIntent={ supportsDeferredIntent }
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

/**
 * Renders a Stripe Payment elements component.
 *
 * TODO: Remove this middle function and use PaymentElements directly (exporting it).
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
