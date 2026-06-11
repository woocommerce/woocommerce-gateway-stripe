import { store as commandsStore } from '@wordpress/commands';
import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * The Stripe settings section, relative to the wp-admin directory.
 */
const STRIPE_SECTION = 'admin.php?page=wc-settings&tab=checkout&section=stripe';

/**
 * Builds the list of Stripe command palette entries.
 *
 * @return {Array<{name: string, label: string, url: string}>} The commands.
 */
const getCommands = () => [
	{
		name: 'woocommerce-gateway-stripe/settings',
		label: __( 'Stripe: Settings', 'woocommerce-gateway-stripe' ),
		url: `${ STRIPE_SECTION }&panel=settings`,
	},
	{
		name: 'woocommerce-gateway-stripe/payment-methods',
		label: __( 'Stripe: Payment methods', 'woocommerce-gateway-stripe' ),
		url: `${ STRIPE_SECTION }&panel=methods`,
	},
	{
		name: 'woocommerce-gateway-stripe/express-checkout',
		label: __( 'Stripe: Express Checkout', 'woocommerce-gateway-stripe' ),
		url: `${ STRIPE_SECTION }&area=express_checkout`,
	},
	{
		name: 'woocommerce-gateway-stripe/amazon-pay',
		label: __( 'Stripe: Amazon Pay', 'woocommerce-gateway-stripe' ),
		url: `${ STRIPE_SECTION }&area=amazon_pay`,
	},
];

/**
 * Registers Stripe admin destinations as WordPress Command Palette commands.
 */
export const registerStripeCommands = () => {
	const commands = dispatch( commandsStore );

	if ( ! commands || typeof commands.registerCommand !== 'function' ) {
		return;
	}

	getCommands().forEach( ( { name, label, url } ) => {
		commands.registerCommand( {
			name,
			label,
			category: 'view',
			callback: ( { close } ) => {
				window.location.href = url;
				close();
			},
		} );
	} );
};

registerStripeCommands();
