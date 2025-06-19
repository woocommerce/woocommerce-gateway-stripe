/**
 * This file is for adding custom checkout field data when using express checkout
 * with classic checkout.
 *
 * It adds the data under extensions, using the wc-stripe/ece-custom-checkout-data namespace.
 */
import { addFilter } from '@wordpress/hooks';
import { getExpressCheckoutData } from 'wcstripe/express-checkout/utils';

addFilter(
	'wcstripe.express-checkout.cart-place-order-extension-data',
	'automattic/wcstripe/express-checkout',
	( extensionData ) => {
		const customCheckoutFields = getExpressCheckoutData(
			'custom_checkout_fields'
		);

		if ( ! customCheckoutFields ) {
			return extensionData;
		}

		const customCheckoutFieldsData = {};
		Object.keys( customCheckoutFields ).forEach( ( field ) => {
			const formElement = document.querySelector(
				`form [name="${ field }"]`
			);
			if ( ! formElement ) {
				return;
			}

			if ( formElement.type === 'checkbox' ) {
				if ( formElement.checked ) {
					customCheckoutFieldsData[ field ] = 1;
				}
			} else {
				customCheckoutFieldsData[ field ] = formElement.value;
			}
		} );

		return {
			...extensionData,
			'wc-stripe/express-checkout': {
				custom_checkout_data: JSON.stringify(
					customCheckoutFieldsData
				),
			},
		};
	}
);
