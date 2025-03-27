import { __ } from '@wordpress/i18n';
import icons from './payment-method-icons';
import {
	PAYMENT_METHOD_ACH,
	PAYMENT_METHOD_ACSS,
	PAYMENT_METHOD_AFFIRM,
	PAYMENT_METHOD_AFTERPAY_CLEARPAY,
	PAYMENT_METHOD_ALIPAY,
	PAYMENT_METHOD_BACS,
	PAYMENT_METHOD_BANCONTACT,
	PAYMENT_METHOD_BECS,
	PAYMENT_METHOD_BLIK,
	PAYMENT_METHOD_BOLETO,
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_CASHAPP,
	PAYMENT_METHOD_EPS,
	PAYMENT_METHOD_GIROPAY,
	PAYMENT_METHOD_IDEAL,
	PAYMENT_METHOD_KLARNA,
	PAYMENT_METHOD_MULTIBANCO,
	PAYMENT_METHOD_OXXO,
	PAYMENT_METHOD_P24,
	PAYMENT_METHOD_SEPA,
	PAYMENT_METHOD_SOFORT,
	PAYMENT_METHOD_WECHAT_PAY,
} from 'wcstripe/stripe-utils/constants';

const accountCountry =
	window.wc_stripe_settings_params?.account_country || 'US';
const isAchEnabled = window.wc_stripe_settings_params?.is_ach_enabled === '1';
const isAcssEnabled = window.wc_stripe_settings_params?.is_acss_enabled === '1';
const isBacsEnabled = window.wc_stripe_settings_params?.is_bacs_enabled === '1';
const isBecsDebitEnabled =
	window.wc_stripe_settings_params?.is_becs_debit_enabled === '1';
const isBlikEnabled = window.wc_stripe_settings_params?.is_blik_enabled === '1';

const paymentMethodsMap = {
	card: {
		id: PAYMENT_METHOD_CARD,
		label: __( 'Credit card / debit card', 'woocommerce-gateway-stripe' ),
		description: __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.card,
		currencies: [],
		allows_manual_capture: true,
	},
	giropay: {
		id: PAYMENT_METHOD_GIROPAY,
		label: __( 'giropay', 'woocommerce-gateway-stripe' ),
		description: __(
			'Expand your business with giropay — Germany’s second most popular payment system.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.giropay,
		currencies: [ 'EUR' ],
	},
	klarna: {
		id: PAYMENT_METHOD_KLARNA,
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
		allows_manual_capture: true,
	},
	affirm: {
		id: PAYMENT_METHOD_AFFIRM,
		label: __( 'Affirm', 'woocommerce-gateway-stripe' ),
		// translators: %s is the store currency.
		description: __(
			'Allow customers to pay over time. Available to all customers paying in %s. Purchases from 50 %s to 30,000 %s are eligible for Affirm financing.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.affirm,
		currencies: [ 'USD', 'CAD' ],
		allows_manual_capture: true,
	},
	// Clearpay and Afterpay are the same payment method, but with different strings and icon.
	afterpay_clearpay: {
		id: PAYMENT_METHOD_AFTERPAY_CLEARPAY,
		label:
			accountCountry === 'GB'
				? __( 'Clearpay', 'woocommerce-gateway-stripe' )
				: __( 'Afterpay', 'woocommerce-gateway-stripe' ),
		description:
			accountCountry === 'GB'
				? __(
						'Allow customers to pay over time with Clearpay. {{limitsLink}}Transaction limits vary by country{{/limitsLink}}.',
						'woocommerce-gateway-stripe'
				  )
				: __(
						'Allow customers to pay over time with Afterpay. {{limitsLink}}Transaction limits vary by country{{/limitsLink}}.',
						'woocommerce-gateway-stripe'
				  ),
		Icon: accountCountry === 'GB' ? icons.clearpay : icons.afterpay,
		currencies: [ 'USD', 'AUD', 'CAD', 'NZD', 'GBP' ],
		allows_manual_capture: true,
	},
	sepa_debit: {
		id: PAYMENT_METHOD_SEPA,
		label: __( 'Direct debit payment', 'woocommerce-gateway-stripe' ),
		description: __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.sepa_debit,
		currencies: [ 'EUR' ],
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
	},
	sofort: {
		id: PAYMENT_METHOD_SOFORT,
		label: __( 'Sofort', 'woocommerce-gateway-stripe' ),
		description: __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, Netherlands, and Spain.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.sofort,
		currencies: [ 'EUR' ],
	},
	eps: {
		id: PAYMENT_METHOD_EPS,
		label: __( 'EPS', 'woocommerce-gateway-stripe' ),
		description: __(
			'EPS is an Austria-based payment method that allows customers to complete transactions online using their bank credentials.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.eps,
		currencies: [ 'EUR' ],
	},
	bancontact: {
		id: PAYMENT_METHOD_BANCONTACT,
		label: __( 'Bancontact', 'woocommerce-gateway-stripe' ),
		description: __(
			'Bancontact is the most popular online payment method in Belgium, with over 15 million cards in circulation.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.bancontact,
		currencies: [ 'EUR' ],
	},
	ideal: {
		id: PAYMENT_METHOD_IDEAL,
		label: __( 'iDEAL', 'woocommerce-gateway-stripe' ),
		description: __(
			'iDEAL is a Netherlands-based payment method that allows customers to complete transactions online using their bank credentials.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.ideal,
		currencies: [ 'EUR' ],
	},
	p24: {
		id: PAYMENT_METHOD_P24,
		label: __( 'Przelewy24', 'woocommerce-gateway-stripe' ),
		description: __(
			'Przelewy24 is a Poland-based payment method aggregator that allows customers to complete transactions online using bank transfers and other methods.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.p24,
		currencies: [ 'EUR', 'PLN' ],
	},
	boleto: {
		id: PAYMENT_METHOD_BOLETO,
		label: __( 'Boleto', 'woocommerce-gateway-stripe' ),
		description: __(
			'Boleto is an official payment method in Brazil. Customers receive a voucher that can be paid at authorized agencies or banks, ATMs, or online bank portals.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.boleto,
		currencies: [ 'BRL' ],
	},
	oxxo: {
		id: PAYMENT_METHOD_OXXO,
		label: __( 'OXXO', 'woocommerce-gateway-stripe' ),
		description: __(
			'OXXO is a Mexican chain of convenience stores that allows customers to pay bills and online purchases in-store with cash.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.oxxo,
		currencies: [ 'MXN' ],
	},
	alipay: {
		id: PAYMENT_METHOD_ALIPAY,
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
	},
	multibanco: {
		id: PAYMENT_METHOD_MULTIBANCO,
		label: __( 'Multibanco', 'woocommerce-gateway-stripe' ),
		description: __(
			'Multibanco is an interbank network that links the ATMs of all major banks in Portugal, allowing customers to pay through either their ATM or online banking environment.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.multibanco,
		currencies: [ 'EUR' ],
	},
	wechat_pay: {
		id: PAYMENT_METHOD_WECHAT_PAY,
		label: __( 'WeChat Pay', 'woocommerce-gateway-stripe' ),
		description: __(
			'WeChat Pay is a popular mobile payment and digital wallet service by WeChat in China.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.wechat_pay,
		currencies: [
			'CNY',
			'AUD',
			'CAD',
			'EUR',
			'GBP',
			'HKD',
			'JPY',
			'SGD',
			'USD',
			'DKK',
			'NOK',
			'SEK',
			'CHF',
		],
	},
	cashapp: {
		id: PAYMENT_METHOD_CASHAPP,
		label: __( 'Cash App Pay', 'woocommerce-gateway-stripe' ),
		description: __(
			'Cash App is a popular consumer app in the US that allows customers to bank, invest, send, and receive money using their digital wallet.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.cashapp,
		currencies: [ 'USD' ],
		capability: 'cashapp_payments',
	},
};

// Enable ACH according to feature flag value.
if ( isAchEnabled ) {
	paymentMethodsMap.us_bank_account = {
		id: PAYMENT_METHOD_ACH,
		label: __( 'ACH Direct Debit', 'woocommerce-gateway-stripe' ),
		description: __(
			'ACH lets you accept payments from customers with a US bank account.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.us_bank_account,
		currencies: [ 'USD' ],
	};
}

// Enable ACSS according to feature flag value.
if ( isAcssEnabled ) {
	paymentMethodsMap.acss_debit = {
		id: PAYMENT_METHOD_ACSS,
		label: __( 'Pre-Authorized Debit', 'woocommerce-gateway-stripe' ),
		description: __(
			'Canadian Pre-Authorized Debit is a payment method that allows customers to pay using their Canadian bank account.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.acss_debit,
		currencies: [ 'CAD' ],
	};
}

// Enable Bacs according to feature flag value.
if ( isBacsEnabled ) {
	paymentMethodsMap.bacs_debit = {
		id: PAYMENT_METHOD_BACS,
		label: 'Bacs Direct Debit',
		description: __(
			'Bacs Direct Debit enables customers in the UK to pay by providing their bank account details.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.bacs_debit,
		currencies: [ 'GBP' ],
	};
}

// Enable BECS Debit according to feature flag value.
if ( isBecsDebitEnabled ) {
	paymentMethodsMap.au_becs_debit = {
		id: PAYMENT_METHOD_BECS,
		label: __( 'BECS Direct Debit', 'woocommerce-gateway-stripe' ),
		description: __(
			'BECS Direct Debit enables customers in Australia to pay by providing their bank account details.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.au_becs_debit,
		currencies: [ 'AUD' ],
	};
}

// Enable BLIK according to feature flag value.
if ( isBlikEnabled ) {
	paymentMethodsMap.blik = {
		id: PAYMENT_METHOD_BLIK,
		label: 'BLIK',
		description: __(
			'BLIK enables customers in Poland to pay directly via online payouts from their bank account.',
			'woocommerce-gateway-stripe'
		),
		Icon: icons.blik,
		currencies: [ 'PLN' ],
	};
}

export default paymentMethodsMap;
