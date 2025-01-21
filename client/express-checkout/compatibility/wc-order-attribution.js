/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { extractOrderAttributionData } from 'wcstripe/blocks/utils';

addFilter(
	'wcstripe.express-checkout.cart-place-order-extension-data',
	'automattic/wcstripe/express-checkout',
	( extensionData ) => {
		const orderAttributionData = {};
		for ( const [ name, value ] of Object.entries(
			extractOrderAttributionData()
		) ) {
			if ( name && value ) {
				orderAttributionData[
					name.replace( 'wc_order_attribution_', '' )
				] = value;
			}
		}
		return {
			...extensionData,
			'woocommerce/order-attribution': orderAttributionData,
		};
	}
);
