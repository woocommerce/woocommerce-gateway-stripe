/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import CreditCardIcon from './payment-method-icons/cards';
import GiropayIcon from './payment-method-icons/giropay';
import SofortIcon from './payment-method-icons/sofort';
import SepaIcon from './payment-method-icons/sepa';

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
