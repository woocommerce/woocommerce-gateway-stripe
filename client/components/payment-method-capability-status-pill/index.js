import React from 'react';
import interpolateComponents from 'interpolate-components';
import { __, sprintf } from '@wordpress/i18n';
import { useGetCapabilities } from 'wcstripe/data/account';
import PaymentMethodUnavailablePill, {
	PaymentMethodPopoverLink,
} from 'wcstripe/components/payment-method-unavailable-pill';

const PaymentMethodCapabilityStatusPill = ( { id, label } ) => {
	const capabilities = useGetCapabilities();
	const capabilityStatus =
		id === 'us_bank_account'
			? capabilities[ `${ id }_ach_payments` ]
			: capabilities[ `${ id }_payments` ];

	return (
		<>
			{ capabilityStatus === 'pending' && (
				<PaymentMethodUnavailablePill
					title={ __(
						'Pending approval',
						'woocommerce-gateway-stripe'
					) }
				>
					{ interpolateComponents( {
						mixedString: sprintf(
							/* translators: %s: a payment method name. */
							__(
								'%s is {{stripeDashboardLink}}pending approval{{/stripeDashboardLink}}. Once approved, you will be able to use it.',
								'woocommerce-gateway-stripe'
							),
							label
						),
						components: {
							stripeDashboardLink: (
								<PaymentMethodPopoverLink href="https://dashboard.stripe.com/settings/payment_methods" />
							),
						},
					} ) }
				</PaymentMethodUnavailablePill>
			) }

			{ capabilityStatus === 'inactive' && (
				<PaymentMethodUnavailablePill
					title={ __(
						'Requires activation',
						'woocommerce-gateway-stripe'
					) }
				>
					{ interpolateComponents( {
						mixedString: sprintf(
							/* translators: %s: a payment method name. */
							__(
								'%s requires activation in your {{stripeDashboardLink}}Stripe dashboard{{/stripeDashboardLink}}. Follow the instructions there and check back soon.',
								'woocommerce-gateway-stripe'
							),
							label
						),
						components: {
							stripeDashboardLink: (
								<PaymentMethodPopoverLink href="https://dashboard.stripe.com/settings/payment_methods" />
							),
						},
					} ) }
				</PaymentMethodUnavailablePill>
			) }
		</>
	);
};

export default PaymentMethodCapabilityStatusPill;
