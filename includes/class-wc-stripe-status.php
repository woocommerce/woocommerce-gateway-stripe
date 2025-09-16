<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Stripe_Status.
 *
 * Integrates with Woo Status pages to offer additional tools and insights for the Stripe extension.
 */
class WC_Stripe_Status {
	/**
	 * Maximum number of subscriptions to process in the detached subscriptions tool.
	 *
	 * @var int
	 */
	private const SUBSCRIPTIONS_DETACHED_LIST_LIMIT = 1000;

	/**
	 * Instance of WC_Gateway_Stripe
	 *
	 * @var WC_Gateway_Stripe
	 */
	private $gateway;

	/**
	 * Instance of WC_Stripe_Account
	 *
	 * @var WC_Stripe_Account
	 */
	private $account;

	/**
	 * WC_Stripe_Status constructor.
	 *
	 * @param WC_Gateway_Stripe $gateway Gateway instance.
	 * @param WC_Stripe_Account $account Account instance.
	 */
	public function __construct( $gateway, $account ) {
		$this->gateway = $gateway;
		$this->account = $account;
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'woocommerce_system_status_report', [ $this, 'render_status_report_section' ], 1 );
		add_filter( 'woocommerce_debug_tools', [ $this, 'debug_tools' ] );
	}

	/**
	 * Renders Stripe information on the status page.
	 */
	public function render_status_report_section() {
		$account_data            = $this->account->get_cached_account_data();
		$express_checkout_helper = new WC_Stripe_Express_Checkout_Helper();
		?>
		<table class="wc_status_table widefat" cellspacing="0">
			<thead>
			<tr>
				<th colspan="3" data-export-label="WooCommerce Stripe Payment Gateway">
					<h2>
						WooCommerce Stripe Payment Gateway
						<span class="woocommerce-help-tip" tabindex="0" aria-label="This section shows any information about the Stripe Payment Gateway."></span>
					</h2>
				</th>
			</tr>
			</thead>
			<tbody>
			<tr>
				<td data-export-label="Version"><?php esc_html_e( 'Version', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help">
					<?php
					/* translators: %s: WooCommerce Stripe Payment Gateway */
					echo wc_help_tip( sprintf( esc_html__( 'The current version of the %s extension.', 'woocommerce-gateway-stripe' ), 'WooCommerce Stripe Payment Gateway' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */
					?>
				</td>
				<td><?php echo esc_html( WC_STRIPE_VERSION ); ?></td>
			</tr>
			<tr>
				<td data-export-label="Account ID"><?php esc_html_e( 'Account ID', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'The Stripe account identifier.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td><?php echo esc_html( $account_data['id'] ?? '' ); ?></td>
			</tr>
			<tr>
				<td data-export-label="Account Email"><?php esc_html_e( 'Account Email', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'The Stripe account email address.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td><?php echo esc_html( $account_data['email'] ?? 'Unknown' ); ?></td>
			</tr>
			<tr>
				<td data-export-label="Test Mode Enabled"><?php esc_html_e( 'Test Mode Enabled', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the payment gateway has test payments enabled.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td>
					<?php
					$is_test = WC_Stripe_Mode::is_test();
					$class   = $is_test ? 'error' : 'yes';
					$icon    = $is_test ? 'no' : 'yes';
					?>
					<mark class="<?php echo esc_attr( $class ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
					<?php
					$is_test ? esc_html_e( 'Yes', 'woocommerce-gateway-stripe' ) : esc_html_e( 'No', 'woocommerce-gateway-stripe' );
					?>
					</mark>
				</td>
			</tr>
			<tr>
				<td data-export-label="OAuth Connected"><?php esc_html_e( 'OAuth Connected', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the Stripe account is connected via OAuth.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td>
					<?php
					$stripe_connect  = woocommerce_gateway_stripe()->connect;
					$mode            = WC_Stripe_Mode::is_test() ? 'test' : 'live';
					$oauth_connected = (bool) $stripe_connect->is_connected_via_oauth( $mode );
					$class           = $oauth_connected ? 'yes' : 'no';
					?>
					<mark class="<?php echo esc_attr( $class ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $class ); ?>"></span>
					<?php $oauth_connected ? esc_html_e( 'Yes', 'woocommerce-gateway-stripe' ) : esc_html_e( 'No', 'woocommerce-gateway-stripe' ); ?>
					</mark>
				</td>
			</tr>
			<tr>
				<td data-export-label="Sync Enabled"><?php esc_html_e( 'Sync Enabled', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the payment methods are synced between Stripe dashboard and the plugin.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td>
					<?php
					$is_pmc_enabled = 'yes' === $this->gateway->get_option( 'pmc_enabled', 'no' );
					$class          = $is_pmc_enabled ? 'yes' : 'error';
					$icon           = $is_pmc_enabled ? 'yes' : 'no';
					?>
					<mark class="<?php echo esc_attr( $class ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
					<?php $is_pmc_enabled ? esc_html_e( 'Yes', 'woocommerce-gateway-stripe' ) : esc_html_e( 'No', 'woocommerce-gateway-stripe' ); ?>
					</mark>
				</td>
			</tr>
			<tr>
				<td data-export-label="Legacy Checkout Experience"><?php esc_html_e( 'Legacy Checkout Experience Enabled', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the payment gateway has the legacy checkout experience enabled.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td>
					<?php
					$legacy_checkout_enabled = ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled();
					$class                   = $legacy_checkout_enabled ? 'no' : 'yes';
					?>
					<mark class="<?php echo esc_attr( $class ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $class ); ?>"></span>
					<?php
					WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ? esc_html_e( 'No', 'woocommerce-gateway-stripe' ) : esc_html_e( 'Yes', 'woocommerce-gateway-stripe' );
					?>
					</mark>
				</td>
			</tr>
			<tr>
				<td data-export-label="Optimized Checkout Enabled"><?php esc_html_e( 'Optimized Checkout Enabled', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the Optimized Checkout Suite is enabled.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td>
					<?php
					$is_oc_enabled = 'yes' === $this->gateway->get_option( 'optimized_checkout_element', 'no' );
					$class         = $is_oc_enabled ? 'yes' : 'no';
					$icon          = $is_oc_enabled ? 'yes' : 'no';
					?>
					<mark class="<?php echo esc_attr( $class ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
						<?php $is_oc_enabled ? esc_html_e( 'Yes', 'woocommerce-gateway-stripe' ) : esc_html_e( 'No', 'woocommerce-gateway-stripe' ); ?>
					</mark>
				</td>
			</tr>
			<tr>
				<td data-export-label="Enabled Payment Methods"><?php esc_html_e( 'Enabled Payment Methods', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'What payment methods are enabled for the store.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td><?php echo esc_html( implode( ',', $this->gateway->get_upe_enabled_payment_method_ids() ) ); ?></td>
			</tr>
			<?php if ( ! WC_Stripe_Feature_Flags::is_stripe_ece_enabled() || ! $express_checkout_helper->is_express_checkout_enabled() ) : ?>
			<tr>
				<td data-export-label="Express Checkout"><?php esc_html_e( 'Express Checkout', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether Express Checkout is enabled.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td>
					<mark class="error"><span class="dashicons dashicons-no"></span>
					<?php
					echo __( 'Disabled', 'woocommerce-gateway-stripe' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */
					?>
					</mark>
				</td>
			</tr>
			<?php else : ?>
			<tr>
				<td data-export-label="Express Checkout"><?php esc_html_e( 'Express Checkout', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether Express Checkout is enabled.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td>
					<mark class="yes"><span class="dashicons dashicons-yes"></span>
					<?php
					$express_checkout_enabled_locations = $express_checkout_helper->get_button_locations();
					$express_checkout_enabled_locations = empty( $express_checkout_enabled_locations ) ? 'no locations enabled' : implode( ',', $express_checkout_enabled_locations );
					echo esc_html__( 'Enabled', 'woocommerce-gateway-stripe' ) . ' (' . esc_html( $express_checkout_enabled_locations ) . ')';
					?>
					</mark>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<td data-export-label="Auth and Capture"><?php esc_html_e( 'Auth and Capture Enabled', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the store has the Auth & Capture feature enabled.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td>
					<?php
					$auth_capture_enabled = $this->gateway->is_automatic_capture_enabled();
					$class                = $auth_capture_enabled ? 'yes' : 'no';
					?>
					<mark class="<?php echo esc_attr( $class ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $class ); ?>"></span>
					<?php
					echo $auth_capture_enabled ? esc_html_e( 'Yes', 'woocommerce-gateway-stripe' ) : esc_html_e( 'No', 'woocommerce-gateway-stripe' );
					?>
					</mark>
				</td>
			</tr>
			<tr>
				<td data-export-label="Logging"><?php esc_html_e( 'Logging Enabled', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether debug logging is enabled and working or not.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td>
					<?php
					$can_log = WC_Stripe_Logger::can_log();
					$class   = $can_log ? 'yes' : 'no';
					?>
					<mark class="<?php echo esc_attr( $class ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $class ); ?>"></span>
					<?php
					$can_log ? esc_html_e( 'Yes', 'woocommerce-gateway-stripe' ) : esc_html_e( 'No', 'woocommerce-gateway-stripe' );
					?>
					</mark>
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Add Stripe tools to the Woo debug tools.
	 *
	 * @param array $tools List of current available tools.
	 */
	public function debug_tools( $tools ) {
		if ( WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() ) {
			$tools['wc_stripe_list_detached_subscriptions'] = [
				'name'     => __( 'List Stripe subscriptions with detached payment method', 'woocommerce-gateway-stripe' ),
				'button'   => __( 'List subscriptions', 'woocommerce-gateway-stripe' ),
				'desc'     => sprintf(
					'%1$s<br/><strong class="red">%2$s</strong> %3$s<br/><strong>%4$s</strong>',
					__( 'This tool will list all Stripe subscriptions with detached payment methods.', 'woocommerce-gateway-stripe' ),
					__( 'Note:', 'woocommerce-gateway-stripe' ),
					__( 'This tool will make an API request to Stripe for each active Stripe subscription in your store that is due to renew in the next month. For stores with many subscriptions, this may temporarily impact performance.', 'woocommerce-gateway-stripe' ),
					__( 'Not recommended if you have more than 100 active subscriptions due for renewal within 30 days.', 'woocommerce-gateway-stripe' ),
				),

				'callback' => [ $this, 'list_detached_subscriptions' ],
			];
		}

		$tools['wc_stripe_database_cache_cleanup'] = [
			'name'     => __( 'Stripe database cache cleanup', 'woocommerce-gateway-stripe' ),
			'button'   => __( 'Clean Stripe cache', 'woocommerce-gateway-stripe' ),
			'desc'     => __( 'This tool will remove stale entries from the Stripe database cache.', 'woocommerce-gateway-stripe' ),
			'callback' => [ $this, 'database_cache_cleanup' ],
		];

		return $tools;
	}

	/**
	 * Lists Stripe subscriptions with detached payment methods.
	 *
	 * @return void
	 */
	public function list_detached_subscriptions() {
		/**
		 * Maximum number of subscriptions to process.
		 *
		 * @since 9.7.0
		 * @param int $max_count The maximum number of subscriptions to process.
		 */
		$max_count         = apply_filters( 'wc_stripe_detached_subscriptions_maximum_count', self::SUBSCRIPTIONS_DETACHED_LIST_LIMIT ); // Limit the number of subscriptions to process for safety.
		$subscriptions     = WC_Stripe_Subscriptions_Helper::get_detached_subscriptions( $max_count );
		$detached_messages = WC_Stripe_Subscriptions_Helper::build_subscriptions_detached_messages( $subscriptions );
		echo '<div class="wrap woocommerce">';
			echo '<h1>' . esc_html__( 'List Detached Stripe Subscriptions', 'woocommerce-gateway-stripe' ) . '</h1>';
		if ( empty( $detached_messages ) ) {
			echo '<div class="notice notice-info inline">';
				echo '<p>' . esc_html__( 'No detached subscriptions found.', 'woocommerce-gateway-stripe' ) . '</p>';
			echo '</div>';
		} else {
			echo '<div class="notice notice-error inline">';
				echo '<p>';
					echo wp_kses(
						$detached_messages,
						[
							'a'      => [
								'href'   => [],
								'target' => [],
							],
							'strong' => [],
							'br'     => [],
						]
					);
				echo '</p>';
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Clean up stale entries from the Stripe database cache.
	 *
	 * @return void
	 */
	public function database_cache_cleanup(): void {
		$result = WC_Stripe_Database_Cache::delete_all_stale_entries( WC_Stripe_Database_Cache::CLEANUP_APPROACH_INLINE, -1 );

		if ( is_wp_error( $result['error'] ) ) {
			echo '<div class="notice notice-error inline">';
				echo '<p>' . esc_html__( 'Error cleaning up Stripe database cache.', 'woocommerce-gateway-stripe' ) . '</p>';
				echo '<p>' . esc_html( $result['error']->get_error_message() ) . '</p>';
			echo '</div>';

			return;
		}

		$result_summary = sprintf(
			/* translators: %1$d: number of entries processed; %2$d: number of stale entries removed */
			__( '%1$d entries processed; %2$d stale entries removed', 'woocommerce-gateway-stripe' ),
			$result['processed'],
			$result['deleted']
		);

		echo '<div class="notice notice-success inline">';
			echo '<p>' . esc_html__( 'Stripe database cache cleaned up successfully.', 'woocommerce-gateway-stripe' ) . '</p>';
			echo '<p>' . esc_html( $result_summary ) . '</p>';
		echo '</div>';
	}
}
