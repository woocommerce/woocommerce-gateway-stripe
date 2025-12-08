import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import PaymentMethodUnavailablePill from 'wcstripe/components/payment-method-unavailable-pill';

const PaymentMethodUnavailableDueTaxSetupPill = ( { label } ) => {
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
