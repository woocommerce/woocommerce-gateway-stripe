import { useState } from '@wordpress/element';
import { Elements, useStripe } from '@stripe/react-stripe-js';
import { useCheckoutSubscriptions } from './use-checkout-subscriptions';
import { InlineCard, CardElements } from './elements';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

/**
 * @typedef {import('../stripe-utils/type-defs').Stripe} Stripe
 * @typedef {import('../stripe-utils/type-defs').StripePaymentRequest} StripePaymentRequest
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').RegisteredPaymentMethodProps} RegisteredPaymentMethodProps
 */

export const getStripeCreditCardIcons = () => {
	return Object.entries( getBlocksConfiguration()?.icons ?? {} ).map(
		( [ id, { src, alt } ] ) => {
			return {
				id,
				src,
				alt,
			};
		}
	);
};

/**
 * Stripe Credit Card component
 *
 * @param {RegisteredPaymentMethodProps} props Incoming props
 */
const CreditCardComponent = ( {
	billing,
	eventRegistration,
	emitResponse,
	components,
} ) => {
	const { ValidationInputError, PaymentMethodIcons } = components;
	const [ sourceId, setSourceId ] = useState( '' );
	const stripe = useStripe();
	const onStripeError = useCheckoutSubscriptions(
		eventRegistration,
		billing,
		sourceId,
		setSourceId,
		emitResponse,
		stripe
	);
	const onChange = ( paymentEvent ) => {
		if ( paymentEvent.error ) {
			onStripeError( paymentEvent );
		}
		setSourceId( '' );
	};
	const cardIcons = getStripeCreditCardIcons();

	const renderedCardElement =
		getBlocksConfiguration()?.inline_cc_form === 'yes' ? (
			<InlineCard
				onChange={ onChange }
				inputErrorComponent={ ValidationInputError }
			/>
		) : (
			<CardElements
				onChange={ onChange }
				inputErrorComponent={ ValidationInputError }
			/>
		);
	return (
		<>
			{ renderedCardElement }
			{ PaymentMethodIcons && cardIcons.length && (
				<PaymentMethodIcons icons={ cardIcons } align="left" />
			) }
		</>
	);
};

export const StripeCreditCard = ( props ) => {
	const { stripe } = props;

	return (
		<Elements stripe={ stripe }>
			<CreditCardComponent { ...props } />
		</Elements>
	);
};
