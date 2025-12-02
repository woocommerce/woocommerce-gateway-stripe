import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import PaymentMethodUnavailablePill from 'wcstripe/components/payment-method-unavailable-pill';
import usePaymentMethodUnavailableReason from 'utils/use-payment-method-unavailable-reason';
import { PAYMENT_METHOD_UNAVAILABLE_REASONS } from 'wcstripe/stripe-utils/constants';

const PaymentMethodUnavailableDueTaxSetupPill = ( { id, label } ) => {
	const unavailableReason = usePaymentMethodUnavailableReason( id );

	if (
		unavailableReason !==
		PAYMENT_METHOD_UNAVAILABLE_REASONS.TAX_BASED_ON_BILLING_ADDRESS
	) {
		return null;
	}

	return (
		<PaymentMethodUnavailablePill
			title={ __(
				'Incompatible tax setup',
				'woocommerce-gateway-stripe'
			) }
		>
			{ sprintf(
				/* translators: $1: a payment method name */
				__(
					"%1$s is unavailable due to the store tax setup being based on the customer's billing address.",
					'woocommerce-gateway-stripe'
				),
				label
			) }
		</PaymentMethodUnavailablePill>
	);
};

export default PaymentMethodUnavailableDueTaxSetupPill;
