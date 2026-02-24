/**
 * External dependencies
 */
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
import {
	useCheckoutSuccessHandler,
	useCheckoutFailHandler,
	usePaymentSetupHandler,
} from '../hooks';
import BlikCodeElement from './blik-code-element';
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
	PAYMENT_METHOD_BLIK,
	PAYMENT_METHOD_CASHAPP,
} from 'wcstripe/stripe-utils/constants';
import { handleDisplayOfPaymentInstructions } from 'wcstripe/optimized-checkout/handle-display-of-payment-instructions';
import { applyStyles } from 'wcstripe/optimized-checkout/apply-styles';
import { handleDisplayOfSavingCheckbox } from 'wcstripe/optimized-checkout/handle-display-of-saving-checkbox';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EmitResponseProps} EmitResponseProps
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EventRegistrationProps} EventRegistrationProps
 */

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
 * @param {*}                      props                     Additional arguments passed for payment processing on the Block Checkout.
 * @param {WCStripeAPI}            props.api                 The Stripe API object.
 * @param {string}                 props.paymentIntentId     The payment intent ID.
 * @param {string}                 props.activePaymentMethod The currently selected/active payment method ID.
 * @param {string}                 props.description         The payment method description to display.
 * @param {string}                 props.testingInstructions The testing instructions to display.
 * @param {EventRegistrationProps} props.eventRegistration   The checkout event emitter registration object.
 * @param {EmitResponseProps}      props.emitResponse        Various helpers for usage with observer response objects.
 * @param {string}                 props.paymentMethodId     The UPE payment method ID.
 * @param {Array}                  props.upeMethods          The UPE methods.
 * @param {string}                 props.errorMessage        The error message to display.
 * @param {boolean}                props.shouldSavePayment   Whether or not to save the payment method.
 * @param {Object}                 props.billing             The checkout billing data.
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
	billing,
	onLoadError = noop,
} ) => {
	const stripe = useStripe();
	const elements = useElements();
	const [ selectedPaymentMethodType, setSelectedPaymentMethodType ] =
		useState( null );
	const [ isPaymentElementComplete, setIsPaymentElementComplete ] =
		useState( false );

	// Make sure shouldSavePayment is set to true if the cart contains a subscription.
	// shouldSavePayment might be set to false because the cart contains a subscription and so the save checkbox isn't shown.
	// If thats the case, we need to force it to true.
	shouldSavePayment =
		shouldSavePayment || getBlocksConfiguration()?.cartContainsSubscription;

	const hasLoadErrorRef = useRef( false );

	const setHasLoadError = ( event ) => {
		hasLoadErrorRef.current = true;
		onLoadError( event );
	};

	useEffect( () => {
		// Show the Cash App limit notice if the payment method is selected and the cart amount is higher than 2000 USD.
		if ( selectedPaymentMethodType === PAYMENT_METHOD_CASHAPP ) {
			maybeShowCashAppLimitNotice(
				'.wc-block-checkout__payment-method .wc-block-components-notices',
				Number( getBlocksConfiguration()?.cartTotal ),
				true
			);
		} else {
			removeCashAppLimitNotice();
		}
		// Apply single payment element styles if the selected payment method is card and OC is enabled.
		if ( getBlocksConfiguration()?.isOCEnabled ) {
			applyStyles();

			// Maybe change the value of `setupFutureUsage` depending on the saving payment method checkbox state.
			const savingPaymentMethodCheckbox = document.querySelector(
				'.wc-block-components-payment-methods__save-card-info input[type=checkbox]'
			);
			savingPaymentMethodCheckbox?.addEventListener(
				'change',
				function () {
					elements.update( {
						setupFutureUsage:
							getBlocksConfiguration()
								?.cartContainsSubscription ||
							savingPaymentMethodCheckbox?.checked
								? 'off_session'
								: null,
					} );
				}
			);
		}
	}, [ selectedPaymentMethodType, elements ] );

	usePaymentSetupHandler(
		activePaymentMethod,
		api,
		billing.billingAddress,
		elements,
		errorMessage,
		hasLoadErrorRef,
		isPaymentElementComplete,
		onPaymentSetup,
		paymentIntentId,
		paymentMethodId,
		selectedPaymentMethodType,
		shouldSavePayment,
		upeMethods
	);

	useCheckoutSuccessHandler(
		api,
		stripe,
		elements,
		onCheckoutSuccess,
		emitResponse,
		shouldSavePayment
	);

	useCheckoutFailHandler(
		api,
		stripe,
		elements,
		onCheckoutFail,
		emitResponse
	);

	const onSelectedPaymentMethodChange = ( { value, complete } ) => {
		setSelectedPaymentMethodType( value.type );
		setIsPaymentElementComplete( complete );
		if ( getBlocksConfiguration()?.isOCEnabled ) {
			handleDisplayOfPaymentInstructions( value.type );
			handleDisplayOfSavingCheckbox( value.type );
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
			{ selectedPaymentMethodType === PAYMENT_METHOD_BLIK ? (
				<BlikCodeElement />
			) : (
				<PaymentElement
					options={ getStripeElementOptions() }
					onChange={ onSelectedPaymentMethodChange }
					onLoadError={ setHasLoadError }
					className="wcstripe-payment-element"
				/>
			) }
		</>
	);
};

export default PaymentProcessor;
