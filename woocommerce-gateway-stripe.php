<?php
/**
 * Plugin Name: WooCommerce Stripe Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-stripe/
 * Description: Accept debit and credit card payments in 135+ currencies, as well as Apple Pay, Google Pay, Klarna, Affirm, P24, ACH, and more.
 * Author: Stripe
 * Author URI: https://stripe.com/
 * Version: 9.8.0
 * Requires Plugins: woocommerce
 * Requires at least: 6.6
 * Tested up to: 6.8.2
 * WC requires at least: 9.8
 * WC tested up to: 10.0
 * Text Domain: woocommerce-gateway-stripe
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_STRIPE_VERSION', '9.8.0' ); // WRCS: DEFINED_VERSION.
define( 'WC_STRIPE_MIN_PHP_VER', '7.4' );
define( 'WC_STRIPE_MIN_WC_VER', '9.8' );
define( 'WC_STRIPE_FUTURE_MIN_WC_VER', '9.9' );
define( 'WC_STRIPE_MAIN_FILE', __FILE__ );
define( 'WC_STRIPE_ABSPATH', __DIR__ . '/' );
define( 'WC_STRIPE_PLUGIN_URL', untrailingslashit( plugin_dir_url( WC_STRIPE_MAIN_FILE ) ) );
define( 'WC_STRIPE_PLUGIN_PATH', untrailingslashit( plugin_dir_path( WC_STRIPE_MAIN_FILE ) ) );

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce fallback notice.
 *
 * @since 4.1.2
 */
function woocommerce_stripe_missing_wc_notice() {
	$install_url = wp_nonce_url(
		add_query_arg(
			[
				'action' => 'install-plugin',
				'plugin' => 'woocommerce',
			],
			admin_url( 'update.php' )
		),
		'install-plugin_woocommerce'
	);

	$admin_notice_content = sprintf(
		// translators: 1$-2$: opening and closing <strong> tags, 3$-4$: link tags, takes to woocommerce plugin on wp.org, 5$-6$: opening and closing link tags, leads to plugins.php in admin
		esc_html__( '%1$sWooCommerce Stripe Gateway is inactive.%2$s The %3$sWooCommerce plugin%4$s must be active for the Stripe Gateway to work. Please %5$sinstall & activate WooCommerce &raquo;%6$s', 'woocommerce-gateway-stripe' ),
		'<strong>',
		'</strong>',
		'<a href="http://wordpress.org/extend/plugins/woocommerce/">',
		'</a>',
		'<a href="' . esc_url( $install_url ) . '">',
		'</a>'
	);

	echo '<div class="error">';
	echo '<p>' . wp_kses_post( $admin_notice_content ) . '</p>';
	echo '</div>';
}

/**
 * WooCommerce not supported fallback notice.
 *
 * @since 4.4.0
 */
function woocommerce_stripe_wc_not_supported() {
	/* translators: $1. Minimum WooCommerce version. $2. Current WooCommerce version. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Stripe requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.', 'woocommerce-gateway-stripe' ), esc_html( WC_STRIPE_MIN_WC_VER ), esc_html( WC_VERSION ) ) . '</strong></p></div>';
}

function woocommerce_gateway_stripe() {

	static $plugin;

	if ( ! isset( $plugin ) ) {
		require_once __DIR__ . '/includes/class-wc-stripe.php';

		$plugin = WC_Stripe::get_instance();
	}

	return $plugin;
}

add_action( 'plugins_loaded', 'woocommerce_gateway_stripe_init' );

function woocommerce_gateway_stripe_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_stripe_missing_wc_notice' );
		return;
	}

	if ( version_compare( WC_VERSION, WC_STRIPE_MIN_WC_VER, '<' ) ) {
		add_action( 'admin_notices', 'woocommerce_stripe_wc_not_supported' );
		return;
	}

	woocommerce_gateway_stripe();
}

/**
 * Add woocommerce_inbox_variant for the Remote Inbox Notification.
 *
 * P2 post can be found at https://wp.me/paJDYF-1uJ.
 */
if ( ! function_exists( 'add_woocommerce_inbox_variant' ) ) {
	function add_woocommerce_inbox_variant() {
		$config_name = 'woocommerce_inbox_variant_assignment';
		if ( false === get_option( $config_name, false ) ) {
			update_option( $config_name, wp_rand( 1, 12 ) );
		}
	}
}
register_activation_hook( __FILE__, 'add_woocommerce_inbox_variant' );

function wcstripe_deactivated() {
	// admin notes are not supported on older versions of WooCommerce.
	require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-upe-compatibility.php';
	if ( class_exists( 'WC_Stripe_Inbox_Notes' ) && WC_Stripe_Inbox_Notes::are_inbox_notes_supported() ) {
		// requirements for the note
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-feature-flags.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-upe-availability-note.php';
		WC_Stripe_UPE_Availability_Note::possibly_delete_note();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-upe-stripelink-note.php';
		WC_Stripe_UPE_StripeLink_Note::possibly_delete_note();
	}
}
register_deactivation_hook( __FILE__, 'wcstripe_deactivated' );

// Hook in Blocks integration. This action is called in a callback on plugins loaded, so current Stripe plugin class
// implementation is too late.
add_action( 'woocommerce_blocks_loaded', 'woocommerce_gateway_stripe_woocommerce_block_support' );

function woocommerce_gateway_stripe_woocommerce_block_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once __DIR__ . '/includes/class-wc-stripe-blocks-support.php';
		// priority is important here because this ensures this integration is
		// registered before the WooCommerce Blocks built-in Stripe registration.
		// Blocks code has a check in place to only register if 'stripe' is not
		// already registered.
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				// I noticed some incompatibility with WP 5.x and WC 5.3 when `_wcstripe_feature_upe_settings` is enabled.
				if ( ! class_exists( 'WC_Stripe_Payment_Request' ) || ! class_exists( 'WC_Stripe_Express_Checkout_Element' ) ) {
					return;
				}

				$container = Automattic\WooCommerce\Blocks\Package::container();
				// registers as shared instance.
				$container->register(
					WC_Stripe_Blocks_Support::class,
					function () {
						if ( class_exists( 'WC_Stripe' ) ) {
							return new WC_Stripe_Blocks_Support( WC_Stripe::get_instance()->payment_request_configuration, WC_Stripe::get_instance()->express_checkout_configuration );
						} else {
							return new WC_Stripe_Blocks_Support();
						}
					}
				);
				$payment_method_registry->register(
					$container->get( WC_Stripe_Blocks_Support::class )
				);
			},
			5
		);
	}
}

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
