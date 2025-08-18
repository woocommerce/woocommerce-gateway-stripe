/* global wc_stripe_settings_params */
import { __, sprintf } from '@wordpress/i18n';
import React from 'react';
import interpolateComponents from 'interpolate-components';
import {
	PAYMENT_METHOD_AFFIRM,
	PAYMENT_METHOD_KLARNA,
} from 'wcstripe/stripe-utils/constants';
import PaymentMethodUnavailablePill, {
	PaymentMethodPopoverLink,
} from 'wcstripe/components/payment-method-unavailable-pill';

const PaymentMethodUnavailableDueConflictPill = ( { id, label } ) => {
	if (
		( id === PAYMENT_METHOD_AFFIRM &&
			// eslint-disable-next-line camelcase
			wc_stripe_settings_params.has_affirm_gateway_plugin ) ||
		( id === PAYMENT_METHOD_KLARNA &&
			// eslint-disable-next-line camelcase
			wc_stripe_settings_params.has_klarna_gateway_plugin )
	) {
		return (
			<PaymentMethodUnavailablePill
				title={ __(
					'Has plugin conflict',
					'woocommerce-gateway-stripe'
				) }
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
	}

	return null;
};

export default PaymentMethodUnavailableDueConflictPill;
