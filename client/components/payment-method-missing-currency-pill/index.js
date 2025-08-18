import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import interpolateComponents from 'interpolate-components';
import { usePaymentMethodCurrencies } from 'utils/use-payment-method-currencies';
import { PAYMENT_METHOD_CARD } from 'wcstripe/stripe-utils/constants';
import PaymentMethodUnavailablePill, {
	PaymentMethodPopoverLink,
} from 'wcstripe/components/payment-method-unavailable-pill';

const PaymentMethodMissingCurrencyPill = ( { id, label } ) => {
	const paymentMethodCurrencies = usePaymentMethodCurrencies( id );
	const storeCurrency = window?.wcSettings?.currency?.code;

	if (
		id !== PAYMENT_METHOD_CARD &&
		! paymentMethodCurrencies.includes( storeCurrency )
	) {
		return (
			<PaymentMethodUnavailablePill
				title={ __(
					'Requires currency',
					'woocommerce-gateway-stripe'
				) }
				popoverContent={ interpolateComponents( {
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
			/>
		);
	}

	return null;
};

export default PaymentMethodMissingCurrencyPill;
