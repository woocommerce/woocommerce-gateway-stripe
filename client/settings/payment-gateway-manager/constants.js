import { __ } from '@wordpress/i18n';

export const gatewaysDescriptions = {
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
