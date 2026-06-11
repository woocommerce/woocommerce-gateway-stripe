import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import PaymentMethodUnavailablePill from 'wcstripe/components/payment-method-unavailable-pill';

const PaymentMethodUnavailableDueConflictPill = ( { label } ) => {
	return (
		<PaymentMethodUnavailablePill
			title={ __( 'Has plugin conflict', 'woocommerce-gateway-stripe' ) }
		>
			{ sprintf(
				/* translators: %1$s: a payment method name */
				__(
					'%1$s is unavailable due to another official plugin being active.',
					'woocommerce-gateway-stripe'
				),
				label
			) }
		</PaymentMethodUnavailablePill>
	);
};

export default PaymentMethodUnavailableDueConflictPill;
