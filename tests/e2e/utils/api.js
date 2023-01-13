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

const throwCustomError = (
	error,
	customMessage = 'Something went wrong. See details below.'
) => {
	throw new Error(
		customMessage
			.concat(
				`\nResponse status: ${ error.response.status } ${ error.response.statusText }`
			)
			.concat(
				`\nResponse headers:\n${ JSON.stringify(
					error.response.headers,
					null,
					2
				) }`
			).concat( `\nResponse data:\n${ JSON.stringify(
			error.response.data,
			null,
			2
		) }
` )
	);
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

		const response = await api
			.post( 'customers', customerParams )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					`Something went wrong when trying to list all shipping methods in shipping zone ${ shippingZoneId }.`
				);
			} );

		return response.data.id;
	},
	product: async ( product ) => {
		const response = await api
			.post( 'products', product )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError( error );
			} );

		return response.data.id;
	},
};

const deletePost = {
	product: async ( id ) => {
		await api.delete( `products/${ id }`, {
			force: true,
		} );
	},
};

module.exports = {
	create,
	deletePost,
};
