import config from 'config';

/**
 * Get subscription product data.
 *
 * @returns {Object} Subscription product data
 */
export function subscriptionData() {
	return {
		...config.get( 'products.subscription' ),
		regular_price: '9.99',
		meta_data: [
			{
				key: '_subscription_period',
				value: 'month',
			},
			{
				key: '_subscription_period_interval',
				value: '1',
			},
		],
	};
}
