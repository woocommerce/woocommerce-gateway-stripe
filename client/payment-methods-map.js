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
			'Boleto is one of the most popular payment method in Brazil',
			'woocommerce-gateway-stripe'
		),
		Icon: BoletoIcon,
		currencies: [ 'BRL' ],
		capability: 'boleto',
	},
	oxxo: {
		id: 'oxxo',
		label: __( 'OXXO', 'woocommerce-gateway-stripe' ),
		description: __(
			'OXXO is a voucher payment widely used in Mexico',
			'woocommerce-gateway-stripe'
		),
		Icon: OxxoIcon,
		currencies: [ 'MXN' ],
		capability: 'oxxo_payments',
	},
};
