import { PaymentElements } from 'wcstripe/blocks/upe/payment-elements';
import { SavedTokenHandler } from 'wcstripe/blocks/upe/components/saved-token-handler';
import {
	getPaymentMethodsConstants,
	PAYMENT_METHOD_BACS,
	PAYMENT_METHOD_CARD,
	EXPRESS_PAYMENT_METHODS,
} from 'wcstripe/stripe-utils/constants';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import WCStripeAPI from 'wcstripe/api';
import { PaymentMethodIcon } from 'wcstripe/blocks/upe/components/payment-method-icon';

const {
	cartContainsSubscription,
	isAdmin,
	isOCEnabled,
	showSavedCards,
	showSaveOption,
	style,
	supports,
} = getBlocksConfiguration() || {};
const upeMethods = getPaymentMethodsConstants();

/**
 * Renders a Stripe Payment elements component.
 *
 * @param {string}      paymentMethodId
 * @param {Array}       methods
 * @param {WCStripeAPI} api
 * @param {string}      description
 * @param {string}      testingInstructions
 * @param {boolean}     showSaveOption
 * @param {boolean}     supportsDeferredIntent
 *
 * @return {JSX.Element} Rendered Payment elements.
 */
const PaymentMethodComponent = ( {
	paymentMethodId,
	methods,
	api,
	description,
	testingInstructions,
	withSaveOption,
	supportsDeferredIntent,
} ) => {
	return (
		<PaymentElements
			paymentMethodId={ paymentMethodId }
			upeMethods={ methods }
			api={ api }
			description={ description }
			testingInstructions={ testingInstructions }
			showSaveOption={ withSaveOption }
			supportsDeferredIntent={ supportsDeferredIntent }
		/>
	);
};

const PaymentMethodLabel = ( { paymentMethod, title } ) => {
	return (
		<>
			<span>
				{ title }
				<PaymentMethodIcon paymentMethod={ paymentMethod } />
			</span>
		</>
	);
};

/**
 * Returns the UPE payment method element for registration.
 *
 * @param {string}      paymentMethod The payment method name.
 * @param {WCStripeAPI} api           The Stripe API object.
 * @param {Object}      upeConfig     The UPE configuration.
 * @return {Object} The UPE payment method configuration.
 */
export const upePaymentMethod = ( paymentMethod, api, upeConfig ) => {
	return {
		name: upeMethods[ paymentMethod ],
		content: (
			<PaymentMethodComponent
				paymentMethodId={ paymentMethod }
				upeMethods={ upeMethods }
				api={ api }
				description={ upeConfig.description }
				testingInstructions={ upeConfig.testingInstructions }
				showSaveOption={ upeConfig.showSaveOption ?? false }
				supportsDeferredIntent={ upeConfig.supportsDeferredIntent }
			/>
		),
		edit: (
			<PaymentMethodComponent
				paymentMethodId={ paymentMethod }
				upeMethods={ upeMethods }
				api={ api }
				description={ upeConfig.description }
				testingInstructions={ upeConfig.testingInstructions }
				showSaveOption={ upeConfig.showSaveOption ?? false }
				supportsDeferredIntent={ upeConfig.supportsDeferredIntent }
			/>
		),
		savedTokenComponent: <SavedTokenHandler api={ api } />,
		canMakePayment: ( cartData ) => {
			// Check if Stripe is available before checking anything else
			if ( ! api.getStripe() ) {
				return false;
			}

			// Check if the payment method is available in the customer's country.
			const isRestrictedInAnyCountry = !! upeConfig.countries.length;
			if (
				isRestrictedInAnyCountry &&
				! upeConfig.countries.includes(
					cartData.billingAddress.country
				)
			) {
				return false;
			}

			// Disable Bacs for subscriptions with a free trial.
			if (
				paymentMethod === PAYMENT_METHOD_BACS &&
				cartContainsSubscription &&
				cartData.cartTotals.total_price === '0'
			) {
				return false;
			}

			// If only express methods are enabled, hide the card method when OC is enabled.
			const nonExpressPaymentMethods =
				upeConfig?.enabledPaymentMethods.filter(
					( method ) => ! EXPRESS_PAYMENT_METHODS.includes( method )
				);
			if (
				paymentMethod === PAYMENT_METHOD_CARD &&
				isOCEnabled &&
				nonExpressPaymentMethods.length === 0
			) {
				return false;
			}

			return true;
		},
		// see .wc-block-checkout__payment-method styles in blocks/style.scss
		label: (
			<PaymentMethodLabel
				paymentMethod={ paymentMethod }
				title={ upeConfig.title }
			/>
		),
		ariaLabel: 'Stripe',
		supports: {
			// Use `false` as fallback values in case server provided configuration is missing.
			showSavedCards: showSavedCards || false,
			showSaveOption: showSaveOption || false,
			features: supports || [],
			style: isAdmin && style ? style : [],
		},
	};
};
