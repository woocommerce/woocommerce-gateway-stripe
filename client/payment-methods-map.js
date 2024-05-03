import { __ } from '@wordpress/i18n';
import icons from './payment-method-icons';

const accountCountry =
	window.wc_stripe_settings_params?.account_country || 'US';

export default {
	card: {
		id: 'card',
		label: __( 'Credit card / debit card', 'woocommerce-gateway-stripe' ),
		description: __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.card,
		currencies: [],
		capability: 'card_payments',
		allows_manual_capture: true,
	},
	giropay: {
		id: 'giropay',
		label: __( 'giropay', 'woocommerce-gateway-stripe' ),
		description: __(
			'Expand your business with giropay — Germany’s second most popular payment system.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.giropay,
		currencies: [ 'EUR' ],
		capability: 'giropay_payments',
	},
	klarna: {
		id: 'klarna',
		label: __( 'Klarna', 'woocommerce-gateway-stripe' ),
		description: __(
			'Allow customers to pay over time with Klarna.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.klarna,
		currencies: [
			'AUD',
			'CAD',
			'CHF',
			'CZK',
			'DKK',
			'EUR',
			'GBP',
			'NOK',
			'NZD',
			'PLN',
			'SEK',
			'USD',
		],
	},
	affirm: {
		id: 'affirm',
		label: __( 'Affirm', 'woocommerce-gateway-stripe' ),
		// translators: %s is the store currency.
		description: __(
			'Allow customers to pay over time with Affirm. Available to all customers paying in %s.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.affirm,
		currencies: [ 'USD', 'CAD' ],
		acceptsDomesticPaymentsOnly: true,
	},
	// Clearpay and Afterpay are the same payment method, but with different strings and icon.
	afterpay_clearpay: {
		id: 'afterpay_clearpay',
		label:
			accountCountry === 'GB'
				? __( 'Clearpay', 'woocommerce-gateway-stripe' )
				: __( 'Afterpay', 'woocommerce-gateway-stripe' ),
		description:
			accountCountry === 'GB'
				? __(
						'Allow customers to pay over time with Clearpay.',
						'woocommerce-gateway-stripe'
				  )
				: __(
						'Allow customers to pay over time with Afterpay.',
						'woocommerce-gateway-stripe'
				  ),
		Icon: accountCountry === 'GB' ? icons.clearpay : icons.afterpay,
		currencies: [ 'USD', 'AUD', 'CAD', 'NZD', 'GBP' ],
	},
	sepa_debit: {
		id: 'sepa_debit',
		label: __( 'Direct debit payment', 'woocommerce-gateway-stripe' ),
		description: __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.sepa_debit,
		currencies: [ 'EUR' ],
		capability: 'sepa_debit_payments',
	},
	sepa: {
		id: 'sepa',
		label: __( 'Direct debit payment', 'woocommerce-gateway-stripe' ),
		description: __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.sepa_debit,
		currencies: [ 'EUR' ],
		capability: 'sepa_debit_payments',
	},
	sofort: {
		id: 'sofort',
		label: __( 'Sofort', 'woocommerce-gateway-stripe' ),
		description: __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, Netherlands, and Spain.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.sofort,
		currencies: [ 'EUR' ],
		capability: 'sofort_payments',
	},
	eps: {
		id: 'eps',
		label: __( 'EPS', 'woocommerce-gateway-stripe' ),
		description: __(
			'EPS is an Austria-based payment method that allows customers to complete transactions online using their bank credentials.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.eps,
		currencies: [ 'EUR' ],
		capability: 'eps_payments',
	},
	bancontact: {
		id: 'bancontact',
		label: __( 'Bancontact', 'woocommerce-gateway-stripe' ),
		description: __(
			'Bancontact is the most popular online payment method in Belgium, with over 15 million cards in circulation.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.bancontact,
		currencies: [ 'EUR' ],
		capability: 'bancontact_payments',
	},
	ideal: {
		id: 'ideal',
		label: __( 'iDEAL', 'woocommerce-gateway-stripe' ),
		description: __(
			'iDEAL is a Netherlands-based payment method that allows customers to complete transactions online using their bank credentials.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.ideal,
		currencies: [ 'EUR' ],
		capability: 'ideal_payments',
	},
	p24: {
		id: 'p24',
		label: __( 'Przelewy24', 'woocommerce-gateway-stripe' ),
		description: __(
			'Przelewy24 is a Poland-based payment method aggregator that allows customers to complete transactions online using bank transfers and other methods.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.p24,
		currencies: [ 'EUR', 'PLN' ],
		capability: 'p24_payments',
	},
	boleto: {
		id: 'boleto',
		label: __( 'Boleto', 'woocommerce-gateway-stripe' ),
		description: __(
			'Boleto is an official payment method in Brazil. Customers receive a voucher that can be paid at authorized agencies or banks, ATMs, or online bank portals.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.boleto,
		currencies: [ 'BRL' ],
		capability: 'boleto_payments',
	},
	oxxo: {
		id: 'oxxo',
		label: __( 'OXXO', 'woocommerce-gateway-stripe' ),
		description: __(
			'OXXO is a Mexican chain of convenience stores that allows customers to pay bills and online purchases in-store with cash.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.oxxo,
		currencies: [ 'MXN' ],
		capability: 'oxxo_payments',
	},
	alipay: {
		id: 'alipay',
		label: __( 'Alipay', 'woocommerce-gateway-stripe' ),
		description: __(
			'Alipay is a popular wallet in China, operated by Ant Financial Services Group, a financial services provider affiliated with Alibaba.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.alipay,
		currencies: [
			'AUD',
			'CAD',
			'CNY',
			'EUR',
			'GBP',
			'HKD',
			'JPY',
			'MYR',
			'NZD',
			'USD',
		],
		capability: 'alipay_payments',
	},
	multibanco: {
		id: 'multibanco',
		label: __( 'Multibanco', 'woocommerce-gateway-stripe' ),
		description: __(
			'Multibanco is an interbank network that links the ATMs of all major banks in Portugal, allowing customers to pay through either their ATM or online banking environment.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.multibanco,
		currencies: [ 'EUR' ],
		capability: 'multibanco_payments',
	},
};
