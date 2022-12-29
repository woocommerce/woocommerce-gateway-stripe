const wcApi = require( '@woocommerce/woocommerce-rest-api' ).default;
const config = require( '../config/playwright.config' );

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

const update = {
	storeDetails: async ( store ) => {
		// ensure store address is US
		const res = await api.post( 'settings/general/batch', {
			update: [
				{
					id: 'woocommerce_store_address',
					value: store.address,
				},
				{
					id: 'woocommerce_store_city',
					value: store.city,
				},
				{
					id: 'woocommerce_default_country',
					value: store.countryCode,
				},
				{
					id: 'woocommerce_store_postcode',
					value: store.zip,
				},
			],
		} );
	},
	enableCashOnDelivery: async () => {
		await api.put( 'payment_gateways/cod', {
			enabled: true,
		} );
	},
	disableCashOnDelivery: async () => {
		await api.put( 'payment_gateways/cod', {
			enabled: false,
		} );
	},
};

const get = {
	coupons: async ( params ) => {
		const response = await api
			.get( 'coupons', params )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Something went wrong when trying to list all coupons.'
				);
			} );

		return response.data;
	},
	defaultCountry: async () => {
		const response = await api.get(
			'settings/general/woocommerce_default_country'
		);

		const code = response.data.default;

		return code;
	},
	orders: async ( params ) => {
		const response = await api
			.get( 'orders', params )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Something went wrong when trying to list all orders.'
				);
			} );

		return response.data;
	},
	products: async ( params ) => {
		const response = await api
			.get( 'products', params )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Something went wrong when trying to list all products.'
				);
			} );

		return response.data;
	},
	productAttributes: async ( params ) => {
		const response = await api
			.get( 'products/attributes', params )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Something went wrong when trying to list all product attributes.'
				);
			} );

		return response.data;
	},
	productCategories: async ( params ) => {
		const response = await api
			.get( 'products/categories', params )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Something went wrong when trying to list all product categories.'
				);
			} );

		return response.data;
	},
	productTags: async ( params ) => {
		const response = await api
			.get( 'products/tags', params )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Something went wrong when trying to list all product tags.'
				);
			} );

		return response.data;
	},
	shippingClasses: async ( params ) => {
		const response = await api
			.get( 'products/shipping_classes', params )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Something went wrong when trying to list all shipping classes.'
				);
			} );

		return response.data;
	},

	shippingZones: async ( params ) => {
		const response = await api
			.get( 'shipping/zones', params )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Something went wrong when trying to list all shipping zones.'
				);
			} );

		return response.data;
	},
	shippingZoneMethods: async ( shippingZoneId ) => {
		const response = await api
			.get( `shipping/zones/${ shippingZoneId }/methods` )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					`Something went wrong when trying to list all shipping methods in shipping zone ${ shippingZoneId }.`
				);
			} );

		return response.data;
	},
	taxClasses: async () => {
		const response = await api
			.get( 'taxes/classes' )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Something went wrong when trying to list all tax classes.'
				);
			} );

		return response.data;
	},
	taxRates: async ( params ) => {
		const response = await api
			.get( 'taxes', params )
			.then( ( response ) => response )
			.catch( ( error ) => {
				throwCustomError(
					error,
					'Something went wrong when trying to list all tax rates.'
				);
			} );

		return response.data;
	},
};

const create = {
	customer: async ( customer ) => {
		const response = await api.post( 'customers', {
			...customer,
			first_name: customer.billing.first_name,
			last_name: customer.billing.last_name,
		} );

		return response.data.id;
	},
};

module.exports = {
	update,
	get,
	create,
	constructWith,
};
