/**
 * External dependencies
 */
import { getPaymentMethods } from '@woocommerce/blocks-registry';
import {
	PaymentElement,
	useElements,
	useStripe,
	Elements,
} from '@stripe/react-stripe-js';
import { useEffect, useState, useRef } from 'react';
/**
 * Internal dependencies
 */
import { usePaymentCompleteHandler, usePaymentFailHandler } from '../hooks';
import RedirectMessageElement from './redirect-message-element';
import BlikCodeElement from './blik-code-element';
import { __ } from '@wordpress/i18n';
import { select } from '@wordpress/data';
import {
	getBlocksConfiguration,
	getStripeElementOptions,
} from 'wcstripe/blocks/utils';
import WCStripeAPI from 'wcstripe/api';
import {
	maybeShowCashAppLimitNotice,
	removeCashAppLimitNotice,
} from 'wcstripe/stripe-utils/cash-app-limit-notice-handler';
import {
	validateBlikCode,
	invalidateAppearanceCache,
	initializeUPEAppearance,
} from 'wcstripe/stripe-utils';
import { sampleFontFamily } from 'wcstripe/styles/upe';
import {
	PAYMENT_METHOD_ACSS,
	PAYMENT_METHOD_BLIK,
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_CASHAPP,
} from 'wcstripe/stripe-utils/constants';
import { handleDisplayOfPaymentInstructions } from 'wcstripe/optimized-checkout/handle-display-of-payment-instructions';
import { applyStyles } from 'wcstripe/optimized-checkout/apply-styles';
import { handleDisplayOfSavingCheckbox } from 'wcstripe/optimized-checkout/handle-display-of-saving-checkbox';

const noop = () => null;

/**
 * Submits the payment elements to Stripe for validation.
 *
 * @param {Elements} elements
 * @return {Promise} Promise that resolves when the elements are validated.
 */
export function validateElements( elements ) {
	return elements.submit().then( ( result ) => {
		if ( result.error ) {
			throw new Error( result.error.message );
		}
	} );
}

/**
 * Renders the payment processor for the Stripe UPE payment method with deferred intent creation.
 *
 * @param {*}           args                     Additional arguments passed for payment processing on the Block Checkout.
 * @param {WCStripeAPI} args.api                 The Stripe API object.
 * @param {string}      args.paymentIntentId     The payment intent ID.
 * @param {string}      args.activePaymentMethod The currently selected/active payment method ID.
 * @param {string}      args.description         The payment method description to display.
 * @param {string}      args.testingInstructions The testing instructions to display.
 * @param {Object}      args.eventRegistration   The checkout event emitter registration object.
 * @param {Object}      args.emitResponse        Various helpers for usage with observer response objects.
 * @param {string}      args.paymentMethodId     The UPE payment method ID.
 * @param {Array}       args.upeMethods          The UPE methods.
 * @param {string}      args.errorMessage        The error message to display.
 * @param {boolean}     args.shouldSavePayment   Whether or not to save the payment method.
 * @param {string}      args.fingerprint         The fingerprint.
 * @param {Object}      args.billing             The checkout billing data.
 *
 * @return {JSX.Element} Rendered payment processor.
 */
const PaymentProcessor = ( {
	api,
	paymentIntentId,
	activePaymentMethod,
	description,
	testingInstructions,
	eventRegistration: { onPaymentSetup, onCheckoutSuccess, onCheckoutFail },
	emitResponse,
	paymentMethodId,
	upeMethods,
	errorMessage,
	shouldSavePayment,
	fingerprint,
	billing,
	onLoadError = noop,
} ) => {
	const stripe = useStripe();
	const elements = useElements();
	const [ selectedPaymentMethodType, setSelectedPaymentMethodType ] =
		useState( null );
	const [ isPaymentElementComplete, setIsPaymentElementComplete ] =
		useState( false );
	const stripeServerData = getBlocksConfiguration();
	const paymentMethodsConfig = stripeServerData?.paymentMethodsConfig;
	const gatewayConfig = getPaymentMethods()[ upeMethods[ paymentMethodId ] ];
	const isBlikSelected = selectedPaymentMethodType === PAYMENT_METHOD_BLIK;

	// Make sure shouldSavePayment is set to true if the cart contains a subscription.
	// shouldSavePayment might be set to false because the cart contains a subscription and so the save checkbox isn't shown.
	// If thats the case, we need to force it to true.
	shouldSavePayment =
		shouldSavePayment || stripeServerData?.cartContainsSubscription;

	const hasLoadErrorRef = useRef( false );

	const setHasLoadError = ( event ) => {
		hasLoadErrorRef.current = true;
		onLoadError( event );
	};

	useEffect(
		() =>
			onPaymentSetup( () => {
				async function handlePaymentProcessing() {
					if (
						upeMethods[ paymentMethodId ] !== activePaymentMethod
					) {
						return;
					}

					if ( hasLoadErrorRef.current ) {
						return {
							type: 'error',
							message: __(
								'Invalid or missing payment details. Please ensure the provided payment method is correctly entered.',
								'woocommerce-gateway-stripe'
							),
						};
					}

					const { validationStore } = window.wc?.wcBlocksData ?? {};
					if ( validationStore ) {
						const store = select( validationStore );
						const hasValidationErrors = store.hasValidationErrors();
						// Return if there is a validation error on the checkout fields.
						if ( hasValidationErrors ) {
							return;
						}
					}

					// BLIK is a special case which is not handled through the Stripe element.
					if ( ! ( isPaymentElementComplete || isBlikSelected ) ) {
						return {
							type: 'error',
							message: __(
								'Your payment information is incomplete.',
								'woocommerce-gateway-stripe'
							),
						};
					}

					if ( errorMessage ) {
						return {
							type: 'error',
							message: errorMessage,
						};
					}

					// Check if user tried to save a method that isn’t reusable.
					if (
						gatewayConfig.supports.showSaveOption &&
						shouldSavePayment &&
						! paymentMethodsConfig[ paymentMethodId ].isReusable
					) {
						return {
							type: 'error',
							message:
								'This payment method cannot be saved for future use.',
						};
					}

					if ( isBlikSelected ) {
						validateBlikCode();
					} else {
						await validateElements( elements );
					}

					const billingAddress = billing.billingAddress;
					const params = {
						billing_details: {
							name: `${ billingAddress.first_name } ${ billingAddress.last_name }`.trim(),
							email: billingAddress.email,
							phone: billingAddress.phone || null, // Phone is optional, but an empty string is not allowed by Stripe.
							address: {
								city: billingAddress.city,
								country: billingAddress.country,
								line1: billingAddress.address_1,
								line2: billingAddress.address_2,
								postal_code: billingAddress.postcode,
								state: billingAddress.state,
							},
						},
					};
					const paymentMethodData = isBlikSelected
						? {
								billing_details: params.billing_details,
								blik: {},
								type: selectedPaymentMethodType,
						  }
						: { elements, params };
					const paymentMethodObject = await api
						.getStripe()
						.createPaymentMethod( paymentMethodData );

					if ( paymentMethodObject.error ) {
						return {
							type: 'error',
							message: paymentMethodObject.error.message,
						};
					}

					const dynamicPaymentData = isBlikSelected
						? {
								'wc-stripe-blik-code': document?.querySelector(
									'#wc-stripe-blik-code'
								)?.value,
						  }
						: {};

					return {
						type: 'success',
						meta: {
							paymentMethodData: {
								...dynamicPaymentData,
								payment_method: upeMethods[ paymentMethodId ],
								wc_payment_intent_id: paymentIntentId ?? '',
								'wc-stripe-payment-method':
									paymentMethodObject.paymentMethod.id,
								save_payment_method: shouldSavePayment
									? 'yes'
									: 'no',
								// The billing information here is relevant to properly create the Stripe Customer object.
								billing_email: billingAddress.email,
								billing_first_name: billingAddress.first_name,
								billing_last_name: billingAddress.last_name,
								billing_address_1: billingAddress.address_1,
								billing_address_2: billingAddress.address_2,
								billing_city: billingAddress.city,
								billing_state: billingAddress.state,
								billing_postcode: billingAddress.postcode,
								billing_country: billingAddress.country,
							},
						},
					};
				}
				return handlePaymentProcessing();
			} ),
		[
			activePaymentMethod,
			api,
			elements,
			fingerprint,
			gatewayConfig,
			paymentMethodId,
			paymentMethodsConfig,
			shouldSavePayment,
			upeMethods,
			errorMessage,
			onPaymentSetup,
			isPaymentElementComplete,
			billing.billingAddress,
			paymentIntentId,
			selectedPaymentMethodType,
			isBlikSelected,
		]
	);

	useEffect( () => {
		// Show the Cash App limit notice if the payment method is selected and the cart amount is higher than 2000 USD.
		if ( selectedPaymentMethodType === PAYMENT_METHOD_CASHAPP ) {
			maybeShowCashAppLimitNotice(
				'.wc-block-checkout__payment-method .wc-block-components-notices',
				Number( stripeServerData?.cartTotal ),
				true
			);
		} else {
			removeCashAppLimitNotice();
		}
		// Apply single payment element styles if the selected payment method is card and OC is enabled.
		if ( stripeServerData?.shouldShowOptimizedCheckout ) {
			applyStyles();
			// Hide the store-level save checkbox on initial load if needed
			// (e.g., when Link is enabled and card is the default method).
			handleDisplayOfSavingCheckbox(
				selectedPaymentMethodType ?? PAYMENT_METHOD_CARD,
				paymentMethodsConfig
			);

			// Maybe change the value of `setupFutureUsage` depending on the saving payment method checkbox state.
			const savingPaymentMethodCheckbox = document.querySelector(
				'.wc-block-components-payment-methods__save-card-info input[type=checkbox]'
			);
			savingPaymentMethodCheckbox?.addEventListener(
				'change',
				function () {
					// `stripe.elements()` exposes `update()`; Adaptive Pricing uses `initCheckout()`, which
					// returns a Checkout object without that API — toggling save-for-later there requires handling the change in the server.
					// not a client-side Elements update.
					// We check for the existence of the `update` function here instead of the 'isAdaptivePricingEnabled' flag
					// because we might be using the payment element as a fallback though the flag is set to true.
					if ( typeof elements.update === 'function' ) {
						elements.update( {
							setupFutureUsage:
								stripeServerData?.cartContainsSubscription ||
								savingPaymentMethodCheckbox?.checked
									? 'off_session'
									: null,
						} );
					}
				}
			);
		}
	}, [
		selectedPaymentMethodType,
		elements,
		stripeServerData,
		paymentMethodsConfig,
	] );

	// After web fonts finish loading, re-compute the appearance so the PE
	// uses the correct font families instead of fallback generics.
	useEffect( () => {
		if ( ! elements ) {
			return;
		}

		let cancelled = false;
		document.fonts?.ready?.then( () => {
			if ( cancelled ) {
				return;
			}

			// Compare the live font with the cached appearance — only
			// invalidate and recompute if they actually differ.
			const cachedFont =
				initializeUPEAppearance( 'true' )?.variables?.fontFamily;
			const liveFont = sampleFontFamily( true );
			if ( ! liveFont || liveFont === cachedFont ) {
				return;
			}

			invalidateAppearanceCache();
			const appearance = initializeUPEAppearance( 'true' );
			if ( typeof elements?.update === 'function' ) {
				elements.update( { appearance } );
			}
		} );

		return () => {
			cancelled = true;
		};
	}, [ elements ] );

	usePaymentCompleteHandler(
		api,
		stripe,
		elements,
		onCheckoutSuccess,
		emitResponse,
		shouldSavePayment
	);

	usePaymentFailHandler(
		api,
		stripe,
		elements,
		onCheckoutFail,
		emitResponse
	);

	const onSelectedPaymentMethodChange = ( { value, complete } ) => {
		setSelectedPaymentMethodType( value.type );
		setIsPaymentElementComplete( complete );
		if ( stripeServerData?.shouldShowOptimizedCheckout ) {
			handleDisplayOfPaymentInstructions( value.type, 'blocks' );
			handleDisplayOfSavingCheckbox( value.type, paymentMethodsConfig );
		}
	};

	return (
		<>
			{ description && (
				<p
					className="content"
					dangerouslySetInnerHTML={ {
						__html: description,
					} }
				/>
			) }
			{ testingInstructions && (
				<p
					className="content"
					dangerouslySetInnerHTML={ {
						__html: testingInstructions,
					} }
				/>
			) }
			{ isBlikSelected ? (
				<BlikCodeElement />
			) : (
				<>
					<PaymentElement
						options={ getStripeElementOptions() }
						onChange={ onSelectedPaymentMethodChange }
						onLoadError={ setHasLoadError }
						className="wcstripe-payment-element"
					/>
					{ paymentMethodId === PAYMENT_METHOD_ACSS && (
						<RedirectMessageElement
							text={ __(
								'After submission, you will need to authorize the payment with your bank.',
								'woocommerce-gateway-stripe'
							) }
						/>
					) }
				</>
			) }
		</>
	);
};

export default PaymentProcessor;
