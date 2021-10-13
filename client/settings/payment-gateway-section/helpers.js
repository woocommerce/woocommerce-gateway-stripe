import { getQuery } from '@woocommerce/navigation';

/**
 * It returns the capitalized payment gateway name. It is deliberately capitalizing only
 * the first letter to match their respective hook names in order to be reusable
 * (e.g. useIsStripeIdealEnabled, useStripeSepaName).
 *
 * @return {string} Capitalized payment gateway
 */
export const getGateway = () => {
	const { section } = getQuery();
	switch ( section ) {
		case 'stripe_sepa':
			return 'Sepa';
		case 'stripe_giropay':
			return 'Giropay';
		case 'stripe_ideal':
			return 'Ideal';
		case 'stripe_bancontact':
			return 'Bancontact';
		case 'stripe_alipay':
			return 'Alipay';
		case 'stripe_multibanco':
			return 'Multibanco';
		default:
			throw new Error( `${ section } is not being hooked.` );
	}
};
