import wcApi from '@woocommerce/woocommerce-rest-api';
import qit from '/qitHelpers';

const config = require( '/qit/tests/e2e/qit-playwright.config' );
let api;

// Ensure that global-setup.js runs before creating api client
if ( qit.getEnv( 'CONSUMER_KEY' ) && qit.getEnv( 'CONSUMER_SECRET' ) ) {
	api = new wcApi( {
		url: config.use.baseURL,
		consumerKey: qit.getEnv( 'CONSUMER_KEY' ),
		consumerSecret: qit.getEnv( 'CONSUMER_SECRET' ),
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

const get = {
	order: async ( orderId ) => {
		const response = await api
			.get( `orders/${ orderId }` )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Failed to get order. See details below.'
				);
			} );

		return response.data;
	},
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
					'Failed to create customer. See details below.'
				);
			} );

		return response.data.id;
	},
	product: async ( product ) => {
		const response = await api
			.post( 'products', product )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Failed to create product. See details below.'
				);
			} );

		return response.data.id;
	},
	order: async ( order ) => {
		const response = await api
			.post( 'orders', order )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Failed to create order. See details below.'
				);
			} );

		return response.data;
	},
};

const update = {
	customer: async ( customer ) => {
		const response = await api
			.put( 'customers', customer )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Failed to update customer. See details below.'
				);
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
	get,
	create,
	update,
	deletePost,
};
