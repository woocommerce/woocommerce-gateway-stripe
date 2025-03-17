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
