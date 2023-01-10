import wcApi from '@woocommerce/woocommerce-rest-api';
import config from '../config/playwright.config';

let api;

// Ensure that global-setup.js runs before creating api client
if ( process.env.CONSUMER_KEY && process.env.CONSUMER_SECRET ) {
	api = new wcApi( {
		url: config.use.baseURL,
		consumerKey: process.env.CONSUMER_KEY,
		consumerSecret: process.env.CONSUMER_SECRET,
		version: 'wc/v3',
	} );
}

/**
 * Allow explicit construction of api client.
 */
const constructWith = ( consumerKey, consumerSecret ) => {
	api = new wcApi( {
		url: config.use.baseURL,
		consumerKey,
		consumerSecret,
		version: 'wc/v3',
	} );
};

const create = {
	customer: async ( customer ) => {
		let customerParams = {
			...customer,
			billing: {
				...customer.billing,
				country: customer.billing.country_iso,
				state: customer.billing.state_iso,
			},
			shipping: {
				...customer.shipping,
				country: customer.shipping.country_iso,
				state: customer.shipping.state_iso,
			},
			first_name: customer.billing.first_name,
			last_name: customer.billing.last_name,
		};

		const response = await api.post( 'customers', customerParams );

		return response.data.id;
	},
};

module.exports = {
	create,
	constructWith,
};
