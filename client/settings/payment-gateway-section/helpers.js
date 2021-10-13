import { getQuery } from '@woocommerce/navigation';

export const getGateway = () => {
	const { section } = getQuery();
	switch ( section ) {
		case 'stripe_sepa':
			return 'Sepa';
		case 'stripe_giropay':
			return 'Giropay';
		case 'stripe_alipay':
			return 'Alipay';
		case 'stripe_multibanco':
			return 'Multibanco';
		default:
			throw new Error( `${ section } is not being hooked.` );
	}
};
