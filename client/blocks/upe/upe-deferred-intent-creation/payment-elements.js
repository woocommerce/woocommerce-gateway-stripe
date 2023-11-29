/**
 * Internal dependencies
 */
import { Elements } from '@stripe/react-stripe-js';
import { getAppearance } from '../../../styles/upe';
import PaymentProcessor from './payment-processor';
import WCStripeAPI from 'wcstripe/api';
import {
	getStripeServerData,
	setStorageWithExpiration,
	getStorageWithExpiration,
	storageKeys,
	getPaymentMethodTypes,
} from 'wcstripe/stripe-utils';

/**
 * Initializes the appearance of the payment element by retrieving the UPE configuration
 * from the API and saving the appearance if it doesn't exist. If the appearance already exists,
 * it is simply returned.
 *
 * @return {Object} The appearance object for the UPE.
 */
function initializeAppearance() {
	const themeName = getStripeServerData()?.theme_name;
	const storageKey = `${ storageKeys.UPE_APPEARANCE }_${ themeName }`;
	let appearance = getStorageWithExpiration( storageKey );

	if ( ! appearance ) {
		appearance = getAppearance();
		const oneDayDuration = 24 * 60 * 60 * 1000;
		setStorageWithExpiration( storageKey, appearance, oneDayDuration );
	}

	return appearance;
}

/**
 * Renders a Stripe Payment elements component.
 *
 * @param {*}           props                 Additional props for payment processing.
 * @param {WCStripeAPI} props.api             Object containing methods for interacting with Stripe.
 * @param {string}      props.paymentMethodId The ID of the payment method.
 *
 * @return {JSX.Element} Rendered Payment elements.
 */
const PaymentElements = ( { api, ...props } ) => {
	const stripe = api.getStripe();
	const amount = Number( getStripeServerData()?.cartTotal );
	const currency = getStripeServerData()?.currency.toLowerCase();
	const appearance = initializeAppearance();

	return (
		<Elements
			stripe={ stripe }
			options={ {
				mode: amount < 1 ? 'setup' : 'payment',
				amount,
				currency,
				paymentMethodCreation: 'manual',
				paymentMethodTypes: getPaymentMethodTypes(
					props.paymentMethodId
				),
				appearance,
			} }
		>
			<PaymentProcessor api={ api } { ...props } />
		</Elements>
	);
};

/**
 * Renders a Stripe Payment elements component.
 *
 * @param {string}      paymentMethodId
 * @param {Array}       upeMethods
 * @param {WCStripeAPI} api
 * @param {string}      testingInstructions
 *
 * @return {JSX.Element} Rendered Payment elements.
 */
export const getDeferredIntentCreationUPEFields = (
	paymentMethodId,
	upeMethods,
	api,
	testingInstructions
) => {
	return (
		<PaymentElements
			paymentMethodId={ paymentMethodId }
			upeMethods={ upeMethods }
			api={ api }
			testingInstructions={ testingInstructions }
		/>
	);
};
