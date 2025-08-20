import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';
import { usePaymentGatewayExpiration } from '../../data/payment-gateway/hooks';

export const gatewaysInfo = {
	stripe_sepa: {
		id: 'sepa_debit',
		title: __( 'SEPA Direct Debit', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: France, Germany, Spain, Belgium, Netherlands, Luxembourg, Italy, Portugal, Austria, Ireland.',
			'woocommerce-gateway-stripe'
		),
		guide:
			'https://stripe.com/payments/payment-methods-guide#sepa-direct-debit',
	},
	stripe_giropay: {
		id: 'giropay',
		title: __( 'giropay', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: Germany.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#giropay',
	},
	stripe_ideal: {
		id: 'ideal',
		title: __( 'iDEAL', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: The Netherlands.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#ideal',
	},
	stripe_bancontact: {
		id: 'bancontact',
		title: __( 'Bancontact', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: Belgium.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#bancontact',
	},
	stripe_eps: {
		id: 'eps',
		title: __( 'EPS', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: Austria.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#eps',
	},
	stripe_sofort: {
		id: 'sofort',
		title: __( 'Sofort', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: Germany, Austria.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#sofort',
	},
	stripe_p24: {
		id: 'p24',
		title: __( 'P24', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: Poland.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#p24',
	},
	stripe_alipay: {
		id: 'alipay',
		title: __( 'Alipay', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: China.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#alipay',
	},
	stripe_multibanco: {
		id: 'multibanco',
		title: __( 'Multibanco', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: Portugal.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#multibanco',
	},
	stripe_boleto: {
		title: __( 'Boleto', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: Brazil.',
			'woocommerce-gateway-stripe'
		),
		guide:
			'https://docs.stripe.com/payments/payment-methods/overview#vouchers',
		Fields: () => {
			const [
				gatewayExpiration,
				setGatewayExpiration,
			] = usePaymentGatewayExpiration();

			return (
				<>
					<h4>
						{ __(
							'Voucher settings',
							'woocommerce-gateway-stripe'
						) }
					</h4>
					<TextControl
						type="number"
						min="0"
						max="60"
						help={ __(
							'Set the number of days until expiration from 0 to 60 days. The default is 3 days.',
							'woocommerce-gateway-stripe'
						) }
						label={ __(
							'Expiration',
							'woocommerce-gateway-stripe'
						) }
						value={ gatewayExpiration }
						onChange={ setGatewayExpiration }
					/>
				</>
			);
		},
	},
	stripe_oxxo: {
		title: __( 'OXXO', 'woocommerce-gateway-stripe' ),
		geography: __(
			'Customer Geography: Mexico.',
			'woocommerce-gateway-stripe'
		),
		guide: 'https://stripe.com/payments/payment-methods-guide#oxxo',
	},
};
