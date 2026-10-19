import { __ } from '@wordpress/i18n';

/**
 * Canonical express checkout button locations, in display order. Shared by the location checkboxes
 * on every Customize express checkouts tab and by the placement simulator, so the keys and labels
 * stay in sync across both. `subscriptionsOnly` locations are only offered when WooCommerce
 * Subscriptions is active (and are not supported by Amazon Pay).
 *
 * Mirrors the setting values read by `WC_Stripe_Express_Checkout_Helper::get_button_locations()`.
 *
 * @return {Array<{key: string, label: string, subscriptionsOnly?: boolean}>} Location definitions.
 */
export const getExpressCheckoutLocationDefinitions = () => [
	{
		key: 'checkout',
		label: __( 'Checkout', 'woocommerce-gateway-stripe' ),
	},
	{
		key: 'product',
		label: __( 'Product page', 'woocommerce-gateway-stripe' ),
	},
	{
		key: 'cart',
		label: __( 'Cart', 'woocommerce-gateway-stripe' ),
	},
	{
		key: 'change_payment_method',
		label: __(
			'Change payment method for WooCommerce Subscriptions',
			'woocommerce-gateway-stripe'
		),
		subscriptionsOnly: true,
	},
];

/**
 * Returns the location keys a tab supports, in display order.
 *
 * @param {Object}  [options]
 * @param {boolean} [options.includeChangePaymentMethod] Include the subscriptions-only location.
 * @return {string[]} Location keys.
 */
export const getExpressCheckoutLocationKeys = ( {
	includeChangePaymentMethod = false,
} = {} ) =>
	getExpressCheckoutLocationDefinitions()
		.filter(
			( location ) =>
				! location.subscriptionsOnly || includeChangePaymentMethod
		)
		.map( ( location ) => location.key );
