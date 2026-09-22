import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { usePaymentMethodCurrencies } from 'utils/use-payment-method-currencies';
import PaymentMethodUnavailablePill from 'wcstripe/components/payment-method-unavailable-pill';

const PaymentMethodMissingCurrencyPill = ( { id, label } ) => {
	const paymentMethodCurrencies = usePaymentMethodCurrencies( id );
	return (
		<PaymentMethodUnavailablePill
			title={ __( 'Requires currency', 'woocommerce-gateway-stripe' ) }
		>
			{ sprintf(
				/* translators: %1$s: Payment method name. %2$s: Supported currency codes. */
				__(
					'%1$s will only be shown at checkout when the customer pays in %2$s',
					'woocommerce-gateway-stripe'
				),
				label,
				paymentMethodCurrencies.join( ', ' )
			) }
		</PaymentMethodUnavailablePill>
	);
};

export default PaymentMethodMissingCurrencyPill;
