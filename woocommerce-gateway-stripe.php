<?php
/**
 * Plugin Name: WooCommerce Stripe Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-stripe/
 * Description: Accept debit and credit card payments in 135+ currencies, as well as Apple Pay, Google Pay, Klarna, Affirm, P24, ACH, and more.
 * Author: Stripe
 * Author URI: https://stripe.com/
 * Version: 11.0.1
 * Requires Plugins: woocommerce
 * Requires at least: 6.9
 * Tested up to: 7.1
 * WC requires at least: 10.9
 * WC tested up to: 11.1
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: woocommerce-gateway-stripe
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_STRIPE_VERSION', '11.0.1' ); // WRCS: DEFINED_VERSION.
define( 'WC_STRIPE_MIN_PHP_VER', '7.4' );
define( 'WC_STRIPE_MIN_WC_VER', '10.8' );
define( 'WC_STRIPE_FUTURE_MIN_WC_VER', '10.9' );
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

/**
 * Initialize the autoloader for the plugin.
 *
 * @since 10.8.0
 * @return bool Return whether the autoloader is available.
 */
function woocommerce_stripe_init_autoloader(): bool {
	static $autoloader_initialized = null;
	if ( true === $autoloader_initialized ) {
		return true;
	}

	$autoloader_initialized = false;

	if ( file_exists( WC_STRIPE_PLUGIN_PATH . '/vendor/autoload.php' ) ) {
		$autoloader_initialized = true;
		require_once WC_STRIPE_PLUGIN_PATH . '/vendor/autoload.php';
	}

	return $autoloader_initialized;
}

function woocommerce_gateway_stripe() {

	static $plugin;

	if ( ! isset( $plugin ) ) {
		woocommerce_stripe_init_autoloader();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe.php';

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

register_activation_hook( __FILE__, 'wc_stripe_set_settings_redirection_transient' );

/**
 * Set a transient to redirect the user to the settings page upon activation.
 *
 * @return void
 */
function wc_stripe_set_settings_redirection_transient(): void {
	set_transient( 'wc_stripe_redirect_to_settings', true, 30 );
}

function wcstripe_deactivated(): void {
	// If we don't have the autoloader available, return early before we call any dependent code.
	// This should only occur in development environments.
	if ( ! woocommerce_stripe_init_autoloader() ) {
		return;
	}

	// admin notes are not supported on older versions of WooCommerce.
	if ( class_exists( 'WC_Stripe_Inbox_Notes' ) && WC_Stripe_Inbox_Notes::are_inbox_notes_supported() ) {
		// requirements for the note
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-feature-flags.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-upe-stripelink-note.php';
		WC_Stripe_UPE_StripeLink_Note::possibly_delete_note();
	}

	require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-database-cache.php';

	WC_Stripe_Database_Cache::unschedule_daily_async_cleanup();

	// Cancel scheduled Agentic Commerce feed syncs.
	if ( interface_exists( 'Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface' ) ) {
		$integration = new WC_Stripe_Agentic_Commerce_Integration();
		$integration->deactivate();
	}
}
register_deactivation_hook( __FILE__, 'wcstripe_deactivated' );

// Hook in Blocks integration. This action is called in a callback on plugins loaded, so current Stripe plugin class
// implementation is too late.
add_action( 'woocommerce_blocks_loaded', 'woocommerce_gateway_stripe_woocommerce_block_support' );

function woocommerce_gateway_stripe_woocommerce_block_support() {
	woocommerce_stripe_init_autoloader();
	WC_Stripe_Checkout_Session_Lifecycle::init_store_api();

	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/class-wc-stripe-blocks-support.php';
		// priority is important here because this ensures this integration is
		// registered before the WooCommerce Blocks built-in Stripe registration.
		// Blocks code has a check in place to only register if 'stripe' is not
		// already registered.
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				// I noticed some incompatibility with WP 5.x and WC 5.3 when `_wcstripe_feature_upe_settings` is enabled.
				if ( ! class_exists( 'WC_Stripe_Express_Checkout_Element' ) ) {
					return;
				}

				$container = Automattic\WooCommerce\Blocks\Package::container();
				// registers as shared instance.
				$container->register(
					WC_Stripe_Blocks_Support::class,
					function () {
						if ( class_exists( 'WC_Stripe' ) ) {
							return new WC_Stripe_Blocks_Support( null, WC_Stripe::get_instance()->express_checkout_configuration );
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

// Temporary debug: dump events Stripe has not delivered successfully. Visit any front-end URL with ?check-failed-webhooks as an admin.
add_action(
	'template_redirect',
	function () {
		if ( ! isset( $_GET['check-failed-webhooks'] ) || ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$cursor_option  = 'wc_stripe_events_cursor_' . ( WC_Stripe_Mode::is_test() ? 'test' : 'live' );
		$cursor         = (int) get_option( $cursor_option, 0 );
		$max_created    = $cursor;
		$starting_after = '';
		$results        = [];

		do {
			$query = [
				'delivery_success' => 'false',
				'limit'            => 100,
			];
			// gte, not gt: other events may share the cursor's second. Already-recorded ones are skipped below.
			if ( $cursor ) {
				$query['created[gte]'] = $cursor;
			}
			if ( $starting_after ) {
				$query['starting_after'] = $starting_after;
			}

			$response = WC_Stripe_API::retrieve( 'events?' . http_build_query( $query ) );
			if ( ! is_object( $response ) || ! empty( $response->error ) ) {
				// Leave the cursor alone so the next run covers this window again.
				$max_created = $cursor;
				break;
			}

			$events = $response->data ?? [];
			if ( ! $events ) {
				break;
			}

			$statuses = wc_stripe_get_event_store()->get_statuses( wp_list_pluck( $events, 'id' ) );

			foreach ( $events as $event ) {
				$status_before = $statuses[ $event->id ] ?? null;

				if ( null === $status_before || WC_Stripe_Event_Store_Interface::STATUS_FAILED === $status_before ) {
					wc_stripe_record_event( $event, WC_Stripe_Event_Store_Interface::STATUS_PENDING );
					$result = 'queued';
				} else {
					$result = 'skipped';
				}

				$max_created = max( $max_created, (int) $event->created );

				$results[] = [
					'id'            => $event->id,
					'type'          => $event->type,
					'created'       => gmdate( 'Y-m-d H:i:s', $event->created ),
					'status_before' => $status_before,
					'result'        => $result,
				];
			}

			$starting_after = end( $events )->id;
		} while ( ! empty( $response->has_more ) );

		if ( $max_created > $cursor ) {
			update_option( $cursor_option, $max_created, false );
		}

		wc_stripe_schedule_pending_events();

		header( 'Content-Type: text/json; charset=utf-8' );
		echo wp_json_encode(
			[
				'cursor_before' => $cursor ? gmdate( 'Y-m-d H:i:s', $cursor ) : null,
				'cursor_after'  => $max_created ? gmdate( 'Y-m-d H:i:s', $max_created ) : null,
				'events'        => $results,
			],
			JSON_PRETTY_PRINT
		);
		exit;
	}
);

function wc_stripe_schedule_pending_events(): void {
	if ( ! as_has_scheduled_action( 'wc_stripe_process_pending_events', [], 'woocommerce-gateway-stripe' ) ) {
		as_enqueue_async_action( 'wc_stripe_process_pending_events', [], 'woocommerce-gateway-stripe' );
	}
}

add_action(
	'wc_stripe_process_pending_events',
	function () {
		$event_ids = wc_stripe_get_event_store()->get_event_ids_by_status( WC_Stripe_Event_Store_Interface::STATUS_PENDING, 5 );

		if ( ! $event_ids ) {
			return;
		}

		$handler = new WC_Stripe_Webhook_Handler();

		foreach ( $event_ids as $event_id ) {
			$event = WC_Stripe_API::retrieve( 'events/' . $event_id );

			if ( ! is_object( $event ) || ! empty( $event->error ) || empty( $event->id ) ) {
				wc_stripe_get_event_store()->save( $event_id, WC_Stripe_Event_Store_Interface::STATUS_FAILED );
				continue;
			}

			try {
				$handler->process_webhook( wp_json_encode( $event ) );
			} catch ( Throwable $e ) {
				wc_stripe_record_event( $event, WC_Stripe_Event_Store_Interface::STATUS_FAILED );
			}
		}

		// The current action is still marked in-progress, so as_has_scheduled_action() would block the next batch.
		as_enqueue_async_action( 'wc_stripe_process_pending_events', [], 'woocommerce-gateway-stripe' );
	}
);

function wc_stripe_get_event_store(): WC_Stripe_Event_Store_Interface {
	return WC_Stripe_Event_Post_Store::get_instance();
}

function wc_stripe_record_event( $notification, string $status, $order = null ): void {
	$event_id = $notification->id ?? '';
	if ( ! is_string( $event_id ) || '' === $event_id ) {
		return;
	}

	$data = [];
	if ( isset( $notification->type ) ) {
		$data['type'] = (string) $notification->type;
	}
	if ( isset( $notification->created ) ) {
		$data['created'] = (int) $notification->created;
	}
	if ( $order instanceof WC_Order ) {
		$data['order_id'] = $order->get_id();
	}

	wc_stripe_get_event_store()->save( $event_id, $status, $data );
}

add_action(
	'wc_stripe_before_process_webhook',
	function ( $webhook_type, $notification ) {
		wc_stripe_record_event( $notification, WC_Stripe_Event_Store_Interface::STATUS_PROCESSING );
	},
	10,
	2
);

add_action(
	'wc_stripe_webhook_received',
	function ( $webhook_type, $notification, $order ) {
		wc_stripe_record_event( $notification, WC_Stripe_Event_Store_Interface::STATUS_PROCESSED, $order );
	},
	10,
	3
);
