/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import CreditCardIcon from './payment-method-icons/cards';
// import BancontactIcon from './payment-method-icons/bancontact';
import GiropayIcon from './payment-method-icons/giropay';
import SofortIcon from './payment-method-icons/sofort';
import SepaIcon from './payment-method-icons/sepa';
// import P24Icon from './payment-method-icons/p24';
// import IdealIcon from './payment-method-icons/ideal';

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
	},
	// bancontact: {
	// 	id: 'bancontact',
	// 	label: __( 'Bancontact', 'woocommerce-gateway-stripe' ),
	// 	description: __(
	// 		'Bancontact is a bank redirect payment method offered by more than 80% of online businesses in Belgium.',
	// 		'woocommerce-gateway-stripe'
	// 	),
	// 	Icon: BancontactIcon,
	// 	currencies: [ 'EUR' ],
	// },
	giropay: {
		id: 'giropay',
		label: __( 'giropay', 'woocommerce-gateway-stripe' ),
		description: __(
			'Expand your business with giropay — Germany’s second most popular payment system.',
			'woocommerce-gateway-stripe'
		),
		Icon: GiropayIcon,
		currencies: [ 'EUR' ],
	},
	// ideal: {
	// 	id: 'ideal',
	// 	label: __( 'iDEAL', 'woocommerce-gateway-stripe' ),
	// 	description: __(
	// 		'Expand your business with iDEAL — Netherlands’s most popular payment method.',
	// 		'woocommerce-gateway-stripe'
	// 	),
	// 	Icon: IdealIcon,
	// 	currencies: [ 'EUR' ],
	// },
	// p24: {
	// 	id: 'p24',
	// 	label: __( 'Przelewy24 (P24)', 'woocommerce-gateway-stripe' ),
	// 	description: __(
	// 		'Accept payments with Przelewy24 (P24), the most popular payment method in Poland.',
	// 		'woocommerce-gateway-stripe'
	// 	),
	// 	Icon: P24Icon,
	// 	currencies: [ 'EUR', 'PLN' ],
	// },
	sepa_debit: {
		id: 'sepa_debit',
		label: __( 'Direct debit payment', 'woocommerce-gateway-stripe' ),
		description: __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-gateway-stripe'
		),
		Icon: SepaIcon,
		currencies: [ 'EUR' ],
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
	},
};
