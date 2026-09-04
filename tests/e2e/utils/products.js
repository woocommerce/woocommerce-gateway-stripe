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
 * Get product data for a free-trial subscription: a simple product with an APFS
 * plan attached (like subscriptionData()) plus a 14-day free trial.
 *
 * APFS hides express checkout on the product page, so this fixture only
 * exercises the cart/checkout express checkout flow.
 *
 * @param {Object}  options
 * @param {boolean} options.virtual Whether the product is virtual (no shipping needed).
 * @returns {Object} Free trial subscription product data
 */
export function freeTrialSubscriptionData( { virtual = true } = {} ) {
	return {
		name: `Free Trial Subscription ${ virtual ? 'Virtual' : 'Physical' }`,
		type: 'simple',
		virtual,
		regular_price: '9.99',
		subscriptionPlan: {
			subscription_period: 'month',
			subscription_period_interval: 1,
			subscription_length: 0,
			subscription_trial_period: 'day',
			subscription_trial_length: 14,
			subscription_pricing_method: 'inherit',
			subscription_discount: 0,
		},
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
