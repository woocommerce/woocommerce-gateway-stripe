import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import { PAYMENT_METHOD_CARD } from 'wcstripe/stripe-utils/constants';
import { useIsOCEnabled } from 'wcstripe/data';
import PaymentMethodUnavailablePill from 'wcstripe/components/payment-method-unavailable-pill';

const PaymentMethodRequiredForOCPill = ( { id, label } ) => {
	const [ isOCEnabled ] = useIsOCEnabled();

	if ( id === PAYMENT_METHOD_CARD && isOCEnabled ) {
		return (
			<PaymentMethodUnavailablePill
				title={ __( 'Required', 'woocommerce-gateway-stripe' ) }
				popoverContent={ sprintf(
					/* translators: $1: a payment method name */
					__(
						'%1$s is required for the Optimized Checkout Suite.',
						'woocommerce-gateway-stripe'
					),
					label
				) }
			/>
		);
	}

	return null;
};

export default PaymentMethodRequiredForOCPill;
