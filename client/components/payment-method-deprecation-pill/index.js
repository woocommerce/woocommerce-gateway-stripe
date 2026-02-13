import React from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';
import PaymentMethodUnavailablePill, {
	PaymentMethodPopoverLink,
} from 'wcstripe/components/payment-method-unavailable-pill';

const PaymentMethodDeprecationPill = () => {
	return (
		<PaymentMethodUnavailablePill
			title={ __( 'Deprecated', 'woocommerce-gateway-stripe' ) }
		>
			{ interpolateComponents( {
				mixedString:
					/* translators: $1: a payment method name. %2: Currency(ies). */
					__(
						'This payment method is deprecated on the {{currencySettingsLink}}legacy checkout as of Oct 29th, 2024{{/currencySettingsLink}}.',
						'woocommerce-gateway-stripe'
					),
				components: {
					currencySettingsLink: (
						<PaymentMethodPopoverLink href="https://woocommerce.com/document/stripe/admin-experience/legacy-checkout-experience/" />
					),
				},
			} ) }
		</PaymentMethodUnavailablePill>
	);
};

export default PaymentMethodDeprecationPill;
