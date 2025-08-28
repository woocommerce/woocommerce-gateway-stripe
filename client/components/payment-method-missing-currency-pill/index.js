import React from 'react';
import interpolateComponents from 'interpolate-components';
import { __, sprintf } from '@wordpress/i18n';
import { usePaymentMethodCurrencies } from 'utils/use-payment-method-currencies';
import usePaymentMethodUnavailableReason from 'utils/use-payment-method-unavailable-reason';
import PaymentMethodUnavailablePill, {
	PaymentMethodPopoverLink,
} from 'wcstripe/components/payment-method-unavailable-pill';
import { PAYMENT_METHOD_UNAVAILABLE_REASONS } from 'wcstripe/stripe-utils/constants';

const PaymentMethodMissingCurrencyPill = ( { id, label } ) => {
	const paymentMethodCurrencies = usePaymentMethodCurrencies( id );
	const unavailableReason = usePaymentMethodUnavailableReason( id );

	if (
		unavailableReason !==
		PAYMENT_METHOD_UNAVAILABLE_REASONS.UNSUPPORTED_CURRENCY
	) {
		return null;
	}

	return (
		<PaymentMethodUnavailablePill
			title={ __( 'Requires currency', 'woocommerce-gateway-stripe' ) }
		>
			{ interpolateComponents( {
				mixedString: sprintf(
					/* translators: $1: a payment method name. %2: Currency(ies). */
					__(
						'%1$s requires store currency to be set to %2$s. {{currencySettingsLink}}Set currency{{/currencySettingsLink}}',
						'woocommerce-gateway-stripe'
					),
					label,
					paymentMethodCurrencies.join( ', ' )
				),
				components: {
					currencySettingsLink: (
						<PaymentMethodPopoverLink href="/wp-admin/admin.php?page=wc-settings&tab=general" />
					),
				},
			} ) }
		</PaymentMethodUnavailablePill>
	);
};

export default PaymentMethodMissingCurrencyPill;
