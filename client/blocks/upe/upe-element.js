import { useState } from '@wordpress/element';
import { PaymentElements } from 'wcstripe/blocks/upe/upe-deferred-intent-creation/payment-elements';
import { SavedTokenHandler } from 'wcstripe/blocks/upe/saved-token-handler';
import {
	getPaymentMethodsConstants,
	PAYMENT_METHOD_AFTERPAY,
	PAYMENT_METHOD_AFTERPAY_CLEARPAY,
	PAYMENT_METHOD_CLEARPAY,
} from 'wcstripe/stripe-utils/constants';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import Icons from 'wcstripe/payment-method-icons';
import { initializeCheckoutIcons } from 'wcstripe/blocks/upe/checkout-icons';
import WCStripeAPI from 'wcstripe/api';
import PaymentProcessor from 'wcstripe/blocks/upe/upe-deferred-intent-creation/payment-processor';

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
		content: (
			<GeneralElement
				api={ api }
				paymentMethod={ paymentMethod }
				upeConfig={ upeConfig }
			/>
		),
		edit: (
			<GeneralElement
				api={ api }
				paymentMethod={ paymentMethod }
				upeConfig={ upeConfig }
			/>
		),
		savedTokenComponent: <SavedTokenHandler api={ api } />,
		canMakePayment: ( cartData ) => {
			const billingCountry = cartData.billingAddress.country;
			const isRestrictedInAnyCountry = !! upeConfig.countries.length;
			const isAvailableInTheCountry =
				! isRestrictedInAnyCountry ||
				upeConfig.countries.includes( billingCountry );

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

/**
 * Get the general element for the UPE.
 *
 * @param {WCStripeAPI} api The Stripe API object.
 * @param {string} paymentMethod The payment method name.
 * @param {Object} upeConfig The UPE configuration.
 * @param {*} props The props.
 * @return {JSX.Element} The general element for the UPE.
 */
const GeneralElement = ( api, paymentMethod, upeConfig, props ) => {
	const [ paymentIntentId ] = useState( null );
	return (
		<PaymentElements
			api={ api }
			paymentMethodId={ paymentMethod }
			showSaveOption={ upeConfig.showSaveOption ?? false }
			supportsDeferredIntent={ upeConfig.supportsDeferredIntent }
		>
			<PaymentProcessor
				api={ api }
				paymentIntentId={ paymentIntentId }
				paymentMethodId={ paymentMethod }
				{ ...props }
			/>
		</PaymentElements>
	);
};
