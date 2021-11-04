import { __ } from '@wordpress/i18n';

export const gatewaysInfo = {
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
	stripe_bancontact: {
		title: __( 'Bancontact', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: Belgium.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#bancontact',
	},
	stripe_eps: {
		title: __( 'EPS', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: Austria.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#eps',
	},
	stripe_sofort: {
		title: __( 'SOFORT', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: Germany, Austria.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#sofort',
	},
	stripe_p24: {
		title: __( 'P24', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: Poland.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#p24',
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
