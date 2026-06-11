import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import PaymentMethodUnavailablePill from 'wcstripe/components/payment-method-unavailable-pill';

const PaymentMethodRequiresCardMethodPill = ( { label } ) => {
	return (
		<PaymentMethodUnavailablePill
			title={ __(
				'Enable credit card / debit card',
				'woocommerce-gateway-stripe'
			) }
		>
			{ sprintf(
				/* translators: %1$s: a payment method name */
				__(
					'Credit card / debit card payment method must be enabled in order to use %1$s.',
					'woocommerce-gateway-stripe'
				),
				label
			) }
		</PaymentMethodUnavailablePill>
	);
};

export default PaymentMethodRequiresCardMethodPill;
