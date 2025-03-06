import { getDeferredIntentCreationUPEFields } from 'wcstripe/blocks/upe/upe-deferred-intent-creation/payment-elements';
import { SavedTokenHandler } from 'wcstripe/blocks/upe/saved-token-handler';
import {
	getPaymentMethodsConstants,
	PAYMENT_METHOD_AFTERPAY,
	PAYMENT_METHOD_AFTERPAY_CLEARPAY,
	PAYMENT_METHOD_CLEARPAY,
	PAYMENT_METHOD_BACS,
} from 'wcstripe/stripe-utils/constants';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import Icons from 'wcstripe/payment-method-icons';
import { initializeCheckoutIcons } from 'wcstripe/blocks/upe/checkout-icons';
import WCStripeAPI from 'wcstripe/api';

// Initialize checkout icons
const isAdmin = getBlocksConfiguration()?.isAdmin ?? false;
const checkoutIcons = initializeCheckoutIcons( isAdmin );

const upeMethods = getPaymentMethodsConstants();

/**
 * Returns the UPE payment method element for registration.
 *
 * @param {string} paymentMethod The payment method name.
 * @param {WCStripeAPI} api The Stripe API object.
 * @param {Object} upeConfig The UPE configuration.
 * @return {Object} The UPE payment method configuration.
 */
export const upeElement = ( paymentMethod, api, upeConfig ) => {
	let iconName = paymentMethod;

	// Afterpay/Clearpay have different icons for UK merchants.
	if ( paymentMethod === PAYMENT_METHOD_AFTERPAY_CLEARPAY ) {
		iconName =
			getBlocksConfiguration()?.accountCountry === 'GB'
				? PAYMENT_METHOD_CLEARPAY
				: PAYMENT_METHOD_AFTERPAY;
	}

	// Use checkout icons if available, otherwise fallback to default Icons
	const Icon =
		( checkoutIcons && checkoutIcons[ iconName ] ) || Icons[ iconName ];
	const supports = {
		// Use `false` as fallback values in case server provided configuration is missing.
		showSavedCards: getBlocksConfiguration()?.showSavedCards ?? false,
		showSaveOption: upeConfig.showSaveOption ?? false,
		features: getBlocksConfiguration()?.supports ?? [],
	};
	if ( getBlocksConfiguration().isAdmin ?? false ) {
		supports.style = getBlocksConfiguration()?.style ?? [];
	}

	return {
		name: upeMethods[ paymentMethod ],
		content: getDeferredIntentCreationUPEFields(
			paymentMethod,
			upeMethods,
			api,
			upeConfig.description,
			upeConfig.testingInstructions,
			upeConfig.showSaveOption ?? false,
			upeConfig.supportsDeferredIntent
		),
		edit: getDeferredIntentCreationUPEFields(
			paymentMethod,
			upeMethods,
			api,
			upeConfig.description,
			upeConfig.testingInstructions,
			upeConfig.showSaveOption ?? false,
			upeConfig.supportsDeferredIntent
		),
		savedTokenComponent: <SavedTokenHandler api={ api } />,
		canMakePayment: ( cartData ) => {
			const billingCountry = cartData.billingAddress.country;
			const isRestrictedInAnyCountry = !! upeConfig.countries.length;
			const isAvailableInTheCountry =
				! isRestrictedInAnyCountry ||
				upeConfig.countries.includes( billingCountry );

			// Disable Bacs for subscriptions with free trial.
			const cartContainsSubscriptions = cartData.cart.cartItems.every(
				( item ) => item.type === 'subscription'
			);
			if (
				paymentMethod === PAYMENT_METHOD_BACS &&
				cartContainsSubscriptions &&
				cartData.cartTotals.total_price === '0'
			) {
				return false;
			}

			return isAvailableInTheCountry && !! api.getStripe();
		},
		// see .wc-block-checkout__payment-method styles in blocks/style.scss
		label: (
			<>
				<span>
					{ upeConfig.title }
					<Icon alt={ upeConfig.title } />
				</span>
			</>
		),
		ariaLabel: 'Stripe',
		supports,
	};
};
