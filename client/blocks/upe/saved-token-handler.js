import { useCheckoutSuccessHandler } from './hooks';
import WCStripeAPI from 'wcstripe/api';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EmitResponseProps} EmitResponseProps
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EventRegistrationProps} EventRegistrationProps
 */

/**
 * Component to handle the payment completion process for saved tokens in the UPE block.
 *
 * @param {Object}                 props                   Component props.
 * @param {WCStripeAPI}            props.api               API client for making requests to the server.
 * @param {Object}                 props.stripe            Stripe.js instance for handling Stripe-related operations.
 * @param {Object}                 props.elements          Stripe Elements instance for handling payment elements.
 * @param {EventRegistrationProps} props.eventRegistration Object containing event registration functions, including onCheckoutAfterProcessingWithSuccess.
 * @param {EmitResponseProps}      props.emitResponse      Function to emit response back to the parent component.
 * @return {JSX.Element} The SavedTokenHandler component, which handles payment completion for saved tokens.
 */
export const SavedTokenHandler = ( {
	api,
	stripe,
	elements,
	eventRegistration: { onCheckoutAfterProcessingWithSuccess },
	emitResponse,
} ) => {
	// Once the server has completed payment processing, confirm the intent of necessary.
	useCheckoutSuccessHandler(
		api,
		stripe,
		elements,
		onCheckoutAfterProcessingWithSuccess,
		emitResponse,
		false // No need to save a payment that has already been saved.
	);

	return <></>;
};
