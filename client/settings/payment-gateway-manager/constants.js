import { __ } from '@wordpress/i18n';

export const gatewaysDescriptions = {
	stripe_sepa: {
		title: 'SEPA Direct Debit',
		geography: __(
			'Customer Geography: France, Germany, Spain, Belgium, Netherlands, Luxembourg, Italy, Portugal, Austria, Ireland.',
			'woocommerce-gateway-stripe'
		),
		guide:
			'https://stripe.com/payments/payment-methods-guide#sepa-direct-debit',
	},
	stripe_alipay: {
		title: 'Alipay',
		geography: __(
			'Customer Geography: China.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#alipay',
	},
	stripe_multibanco: {
		title: 'Multibanco',
		geography: __(
			'Customer Geography: Portugal.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#multibanco',
	},
};
