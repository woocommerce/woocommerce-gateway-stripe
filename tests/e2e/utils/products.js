import config from 'config';

/**
 * Get pre-order product data.
 *
 * @returns {Object} Pre-order product data
 */
export function preOrderData() {
	return {
		...config.get( 'products.pre-order' ),
		regular_price: '19.99',
		meta_data: [
			{
				key: '_wc_pre_orders_enabled',
				value: 'yes',
			},
			{
				key: '_wc_pre_orders_when_to_charge',
				value: 'upon_release',
			},
			{
				key: '_wc_pre_orders_availability_datetime',
				value: ( () => {
					const date = new Date();
					date.setDate( date.getDate() + 7 );

					return Math.round( date.getTime() / 1000 );
				} )(),
			},
			{
				key: '_wc_pre_orders_fee',
				value: '4.99',
			},
		],
	};
}

/**
 * Get product data for a native WCS subscription product with a free trial.
 *
 * Unlike subscriptionData(), which attaches an APFS plan to a simple product,
 * this targets a `subscription`-type product so trial detection
 * (WC_Subscriptions_Product::get_trial_length) also works on the product page,
 * where express checkout reads the trial from the product rather than the cart.
 *
 * The type is `simple` because the WC REST API only accepts core product
 * types; after creating the product, flip it to `subscription` with
 * setProductType() from utils/wp-cli.js — the subscription meta set here is
 * inert until then.
 *
 * @param {Object}  options
 * @param {boolean} options.virtual Whether the product is virtual (no shipping needed).
 *
 * @returns {Object} Free trial subscription product data
 */
export function freeTrialSubscriptionData( { virtual = true } = {} ) {
	return {
		name: `Free Trial Subscription ${ virtual ? 'Virtual' : 'Physical' }`,
		type: 'simple',
		virtual,
		regular_price: '9.99',
		meta_data: [
			{ key: '_subscription_price', value: '9.99' },
			{ key: '_subscription_period', value: 'month' },
			{ key: '_subscription_period_interval', value: '1' },
			{ key: '_subscription_length', value: '0' },
			{ key: '_subscription_trial_length', value: '14' },
			{ key: '_subscription_trial_period', value: 'day' },
		],
	};
}

/**
 * Get subscription product data.
 *
 * @returns {Object} Subscription product data
 */
export function subscriptionData() {
	return {
		...config.get( 'products.subscription' ),
		regular_price: '9.99',
		subscriptionPlan: {
			subscription_period: 'month',
			subscription_period_interval: 1,
		},
	};
}
