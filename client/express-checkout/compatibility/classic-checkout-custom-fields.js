/**
 * This file is for passing custom checkout field data to Store API, for when
 * using express checkout with classic checkout.
 *
 * It extracts the data from the form, and passes the data under extensions, using
 * the wc-stripe/express-checkout namespace.
 */
import { addFilter } from '@wordpress/hooks';
import { getExpressCheckoutData } from 'wcstripe/express-checkout/utils';

addFilter(
	'wcstripe.express-checkout.cart-place-order-extension-data',
	'automattic/wcstripe/express-checkout',
	( extensionData ) => {
		// List of fields we are interested in.
		const customCheckoutFields = getExpressCheckoutData(
			'custom_checkout_fields'
		);

		if ( ! customCheckoutFields ) {
			return extensionData;
		}

		// Extract the data from the checkout form.
		const customCheckoutFieldsData = {};
		const form = document.querySelector( 'form[name="checkout"]' );
		if ( ! form ) {
			return extensionData;
		}

		const formData = new FormData( form );
		for ( const [ fieldName, fieldValue ] of formData.entries() ) {
			const isMultiSelect = fieldName.endsWith( '[]' );
			const key = isMultiSelect ? fieldName.slice( 0, -2 ) : fieldName;
			if ( Object.keys( customCheckoutFields ).includes( key ) ) {
				if ( isMultiSelect ) {
					if ( ! customCheckoutFieldsData[ key ] ) {
						customCheckoutFieldsData[ key ] = [];
					}
					customCheckoutFieldsData[ key ].push( fieldValue );
				} else {
					customCheckoutFieldsData[ key ] = fieldValue;
				}
			}
		}

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
