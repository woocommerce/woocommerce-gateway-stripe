import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import interpolateComponents from 'interpolate-components';
import PaymentMethodUnavailablePill, {
	PaymentMethodPopoverLink,
} from 'wcstripe/components/payment-method-unavailable-pill';
import usePaymentMethodUnavailableReason from 'utils/use-payment-method-unavailable-reason';
import { PAYMENT_METHOD_UNAVAILABLE_REASONS } from 'wcstripe/stripe-utils/constants';

const PaymentMethodUnavailableDueConflictPill = ( { id, label } ) => {
	const unavailableReason = usePaymentMethodUnavailableReason( id );

	if (
		unavailableReason !==
		PAYMENT_METHOD_UNAVAILABLE_REASONS.OFFICIAL_PLUGIN_CONFLICT
	) {
		return null;
	}

	return (
		<PaymentMethodUnavailablePill
			title={ __( 'Has plugin conflict', 'woocommerce-gateway-stripe' ) }
			popoverContent={ interpolateComponents( {
				mixedString: sprintf(
					/* translators: $1: a payment method name */
					__(
						'%1$s is unavailable due to another official plugin being active.',
						'woocommerce-gateway-stripe'
					),
					label
				),
				components: {
					currencySettingsLink: (
						<PaymentMethodPopoverLink href="/wp-admin/admin.php?page=wc-settings&tab=general" />
					),
				},
			} ) }
		/>
	);
};

export default PaymentMethodUnavailableDueConflictPill;
