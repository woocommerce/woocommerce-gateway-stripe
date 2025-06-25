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
		Object.keys( customCheckoutFields ).forEach( ( field ) => {
			const formElements = document.querySelectorAll(
				`form[name="checkout"] [name="${ field }"]`
			);
			if ( ! formElements || formElements.length === 0 ) {
				return;
			}

			formElements.forEach( ( formElement ) => {
				if ( formElement.type === 'checkbox' ) {
					if ( formElement.checked ) {
						customCheckoutFieldsData[ field ] = 1;
					}
				} else if ( formElement.type === 'radio' ) {
					if ( formElement.checked ) {
						customCheckoutFieldsData[ field ] = formElement.value;
					}
				} else {
					customCheckoutFieldsData[ field ] = formElement.value;
				}
			} );
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
