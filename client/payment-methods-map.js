import { __ } from '@wordpress/i18n';
import CreditCardIcon from './payment-method-icons/cards';
import GiropayIcon from './payment-method-icons/giropay';
import SofortIcon from './payment-method-icons/sofort';
import SepaIcon from './payment-method-icons/sepa';
import EpsIcon from './payment-method-icons/eps';
import BancontactIcon from './payment-method-icons/bancontact';
import IdealIcon from './payment-method-icons/ideal';
import P24Icon from './payment-method-icons/p24';
import BoletoIcon from './payment-method-icons/boleto';
import OxxoIcon from './payment-method-icons/oxxo';
import AlipayIcon from './payment-method-icons/alipay';
import MultibancoIcon from './payment-method-icons/multibanco';

const getAlipayCurrencies = () => {
	let upeCurrencies = [];

	const nonUpeCurrencies = [
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
	];

	// cached_account_data = WC_Stripe::get_instance()->account->get_cached_account_data();
	const country = 'US'; //cached_account_data['country'] ?? null;

	switch ( country ) {
		case 'AUS':
			upeCurrencies = [ 'AUD', 'CNY' ];
			break;
		case 'Canada':
			upeCurrencies = [ 'CAD', 'CNY' ];
			break;
		case 'UK':
			upeCurrencies = [ 'GBP', 'CNY' ];
			break;
		case 'Hongkong':
			upeCurrencies = [ 'HKD', 'CNY' ];
			break;
		case 'Japan':
			upeCurrencies = [ 'JPY', 'CNY' ];
			break;
		case 'Malaysia':
			upeCurrencies = [ 'MYR', 'CNY' ];
			break;
		case 'NZ':
			upeCurrencies = [ 'NZD', 'CNY' ];
			break;
		case 'Singapore':
			upeCurrencies = [ 'SGD', 'CNY' ];
			break;
		case 'US':
			upeCurrencies = [ 'USD', 'CNY' ];
			break;
		default:
			upeCurrencies = [ 'CNY' ];
	}

	const EuroSupportedCountries = [
		'AT',
		'BE',
		'BG',
		'CY',
		'CZ',
		'DK',
		'EE',
		'FI',
		'FR',
		'DE',
		'GR',
		'IE',
		'IT',
		'LV',
		'LT',
		'LU',
		'MT',
		'NL',
		'NO',
		'PT',
		'RO',
		'SK',
		'SI',
		'ES',
		'SE',
		'CH',
	];
	if ( EuroSupportedCountries.includes( country ) ) {
		upeCurrencies = [ 'EUR', 'CNY' ];
	}

	return { nonUpeCurrencies, upeCurrencies };
};

export default {
	card: {
		id: 'card',
		label: __( 'Credit card / debit card', 'woocommerce-gateway-stripe' ),
		description: __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-gateway-stripe'
		),
		Icon: CreditCardIcon,
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
		Icon: GiropayIcon,
		currencies: [ 'EUR' ],
		capability: 'giropay_payments',
	},
	sepa_debit: {
		id: 'sepa_debit',
		label: __( 'Direct debit payment', 'woocommerce-gateway-stripe' ),
		description: __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-gateway-stripe'
		),
		Icon: SepaIcon,
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
		Icon: SepaIcon,
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
		Icon: SofortIcon,
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
		Icon: EpsIcon,
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
		Icon: BancontactIcon,
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
		Icon: IdealIcon,
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
		Icon: P24Icon,
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
		Icon: BoletoIcon,
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
		Icon: OxxoIcon,
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
		Icon: AlipayIcon,
		currencies: getAlipayCurrencies(),
		capability: 'alipay_payments',
	},
	multibanco: {
		id: 'multibanco',
		label: __( 'Multibanco', 'woocommerce-gateway-stripe' ),
		description: __(
			'Multibanco is an interbank network that links the ATMs of all major banks in Portugal, allowing customers to pay through either their ATM or online banking environment.',
			'woocommerce-gateway-stripe'
		),
		Icon: MultibancoIcon,
		currencies: [ 'EUR' ],
		capability: 'multibanco_payments',
	},
};
