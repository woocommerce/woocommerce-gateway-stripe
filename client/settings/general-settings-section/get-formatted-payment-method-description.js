import interpolateComponents from 'interpolate-components';
import PaymentMethodsMap from '../../payment-methods-map';
import {
	PAYMENT_METHOD_AFFIRM,
	PAYMENT_METHOD_AFTERPAY_CLEARPAY,
} from 'wcstripe/stripe-utils/constants';
import { sprintf } from '@wordpress/i18n';

/**
 * Formats the payment method description with the account default currency.
 *
 * @param {*} method                 Payment method ID.
 * @param {*} accountDefaultCurrency Account default currency.
 */
export const getFormattedPaymentMethodDescription = (
	method,
	accountDefaultCurrency
) => {
	const { description, minAmounts } = PaymentMethodsMap[ method ];

	if ( method === PAYMENT_METHOD_AFFIRM && minAmounts ) {
		const currency = accountDefaultCurrency?.toUpperCase();
		return sprintf(
			description,
			currency,
			minAmounts[ currency ],
			currency,
			currency
		);
	}

	if ( method === PAYMENT_METHOD_AFTERPAY_CLEARPAY ) {
		/* eslint-disable jsx-a11y/anchor-has-content */
		return interpolateComponents( {
			mixedString: description,
			components: {
				limitsLink: (
					<a
						target="_blank"
						rel="noreferrer"
						href="https://docs.stripe.com/payments/afterpay-clearpay#collection-schedule"
					/>
				),
			},
		} );
		/* eslint-enable jsx-a11y/anchor-has-content */
	}

	return description;
};
