import { __ } from '@wordpress/i18n';

export const gatewaysDescriptions = {
	stripe_sepa: {
		title: __( 'SEPA Direct Debit', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: France, Germany, Spain, Belgium, Netherlands, Luxembourg, Italy, Portugal, Austria, Ireland.',
			'woocommerce-gateway-stripe'
		),
		guide:
			'https://stripe.com/payments/payment-methods-guide#sepa-direct-debit',
	},
	stripe_giropay: {
		title: __( 'giropay', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: Germany.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#giropay',
	},
	stripe_ideal: {
		title: __( 'iDeal', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: The Netherlands.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#ideal',
	},
	stripe_alipay: {
		title: __( 'Alipay', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: China.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#alipay',
	},
	stripe_multibanco: {
		title: __( 'Multibanco', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: Portugal.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#multibanco',
	},
};
