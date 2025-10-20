<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that represents admin notices.
 *
 * @since 4.1.0
 */
class WC_Stripe_Admin_Notices {
	/**
	 * Stripe customer page base URL.
	 *
	 * @var string
	 */
	private const STRIPE_CUSTOMER_PAGE_BASE_URL = 'https://dashboard.stripe.com/customers/';

	/**
	 * Notices (array)
	 *
	 * @var array
	 */
	public $notices = [];

	/**
	 * Constructor
	 *
	 * @since 4.1.0
	 */
	public function __construct() {
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'wp_loaded', [ $this, 'hide_notices' ] );
		add_action( 'woocommerce_stripe_updated', [ $this, 'stripe_updated' ] );
		add_action( 'after_plugin_row_woocommerce-gateway-stripe/woocommerce-gateway-stripe.php', [ $this, 'display_legacy_deprecation_notice' ], 10, 1 );
	}

	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication).
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 */
	public function add_admin_notice( $slug, $class, $message, $dismissible = false, $actions = [] ) {
		$this->notices[ $slug ] = [
			'class'       => $class,
			'message'     => $message,
			'dismissible' => $dismissible,
			'actions'     => $actions,
		];
	}

	/**
	 * Display any notices we've collected thus far.
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Main Stripe payment method.
		$this->stripe_check_environment();

		// All other payment methods.
		$this->payment_methods_check_environment();

		// Check for subscriptions detached from the customer.
		if ( WC_Stripe_Subscriptions_Helper::is_subscriptions_enabled() ) {
			$this->subscription_check_detachment();
			$this->subscription_check_detachment_bulk_action();
		}

		foreach ( (array) $this->notices as $notice_key => $notice ) {
			$actions     = $notice['actions'] ?? [];
			$div_style   = 'position:relative;';
			$has_actions = count( $actions ) > 0;
			if ( $has_actions ) {
				// If there are actions, we need to make sure the div can contain them.
				$div_style .= 'overflow: auto;';
			}

			echo '<div class="' . esc_attr( $notice['class'] ) . '" style="' . esc_attr( $div_style ) . '">';

			if ( $notice['dismissible'] ) {
				?>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-stripe-hide-notice', $notice_key ), 'wc_stripe_hide_notices_nonce', '_wc_stripe_notice_nonce' ) ); ?>" class="woocommerce-message-close notice-dismiss" style="position:relative;float:right;padding:9px 0px 9px 9px 9px;text-decoration:none;"></a>
				<?php
			}

			echo '<p>';
			echo wp_kses(
				$notice['message'],
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

			if ( $has_actions ) {
				foreach ( $actions as $action ) {
					echo wp_kses(
						$action,
						[
							'a' => [
								'href'  => [],
								'style' => [],
							],
						]
					);
				}
			}

			echo '</div>';
		}
	}

	/**
	 * Displays the legacy deprecation notice.
	 *
	 * @param string $plugin_file Plugin file.
	 */
	public static function display_legacy_deprecation_notice( $plugin_file ) {
		global $wp_list_table;
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();

		// If Stripe is not enabled, don't show the legacy deprecation notice.
		if ( ! isset( $stripe_settings['enabled'] ) || 'no' === $stripe_settings['enabled'] ) {
			return;
		}

		if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			return;
		}

		if ( is_null( $wp_list_table ) ) {
			return;
		}

		$columns_count   = $wp_list_table->get_column_count();
		$is_active       = is_plugin_active( $plugin_file );
		$is_active_class = $is_active ? 'active' : 'inactive';

		$setting_link = esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings' ) );
		$message      = sprintf(
			/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
			__( 'WooCommerce Stripe Gateway legacy checkout experience has been deprecated since version 9.6.0. Please %1$smigrate to the new checkout experience%2$s to access more payment methods and avoid disruptions. %3$sLearn more%4$s', 'woocommerce-gateway-stripe' ),
			'<a href="' . $setting_link . '">',
			'</a>',
			'<a href="https://woocommerce.com/document/stripe/admin-experience/legacy-checkout-experience/" target="_blank">',
			'</a>'
		);

		?>
		<tr class='plugin-update-tr <?php echo esc_html( $is_active_class ); ?>' data-id="woocommerce-gateway-stripe-update" data-slug="woocommerce-gateway-stripe" data-plugin='<?php echo esc_html( $plugin_file ); ?>'>
			<td colspan='<?php echo esc_html( $columns_count ); ?>' class='plugin-update colspanchange'>
				<div class='notice inline notice-warning notice-alt'>
					<p>
						<span style="display: inline-block; vertical-align: text-top;">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20Z" stroke="#dba617" stroke-width="1.5"/>
								<path d="M13 7H11V13H13V7Z" fill="#dba617"/>
								<path d="M13 15H11V17H13V15Z" fill="#dba617"/>
							</svg>
						</span>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $message;
						?>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * List of available payment methods.
	 *
	 * @since 4.1.0
	 * @return array
	 */
	public function get_payment_methods() {
		return [
			WC_Stripe_Payment_Methods::ALIPAY     => WC_Gateway_Stripe_Alipay::class,
			WC_Stripe_Payment_Methods::BANCONTACT => WC_Gateway_Stripe_Bancontact::class,
			WC_Stripe_Payment_Methods::EPS        => WC_Gateway_Stripe_Eps::class,
			WC_Stripe_Payment_Methods::GIROPAY    => WC_Gateway_Stripe_Giropay::class,
			WC_Stripe_Payment_Methods::IDEAL      => WC_Gateway_Stripe_Ideal::class,
			WC_Stripe_Payment_Methods::MULTIBANCO => WC_Gateway_Stripe_Multibanco::class,
			WC_Stripe_Payment_Methods::P24        => WC_Gateway_Stripe_P24::class,
			WC_Stripe_Payment_Methods::SEPA       => WC_Gateway_Stripe_Sepa::class,
			WC_Stripe_Payment_Methods::SOFORT     => WC_Gateway_Stripe_Sofort::class,
			WC_Stripe_Payment_Methods::BOLETO     => WC_Gateway_Stripe_Boleto::class,
			WC_Stripe_Payment_Methods::OXXO       => WC_Gateway_Stripe_Oxxo::class,
		];
	}

	/**
	 * The backup sanity check, in case the plugin is activated in a weird way,
	 * or the environment changes after activation. Also handles upgrade routines.
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 */
	public function stripe_check_environment() {
		$show_style_notice         = get_option( 'wc_stripe_show_style_notice' );
		$show_ssl_notice           = get_option( 'wc_stripe_show_ssl_notice' );
		$show_keys_notice          = get_option( 'wc_stripe_show_keys_notice' );
		$show_3ds_notice           = get_option( 'wc_stripe_show_3ds_notice' );
		$show_phpver_notice        = get_option( 'wc_stripe_show_phpver_notice' );
		$show_wcver_notice         = get_option( 'wc_stripe_show_wcver_notice' );
		$show_curl_notice          = get_option( 'wc_stripe_show_curl_notice' );
		$show_sca_notice           = get_option( 'wc_stripe_show_sca_notice' );
		$changed_keys_notice       = get_option( 'wc_stripe_show_changed_keys_notice' );
		$legacy_deprecation_notice = get_option( 'wc_stripe_show_legacy_deprecation_notice' );
		$oauth_required_notice     = get_option( 'wc_stripe_oauth_required' );
		$options                   = WC_Stripe_Helper::get_stripe_settings();
		$testmode                  = WC_Stripe_Mode::is_test();
		$test_pub_key              = isset( $options['test_publishable_key'] ) ? $options['test_publishable_key'] : '';
		$test_secret_key           = isset( $options['test_secret_key'] ) ? $options['test_secret_key'] : '';
		$live_pub_key              = isset( $options['publishable_key'] ) ? $options['publishable_key'] : '';
		$live_secret_key           = isset( $options['secret_key'] ) ? $options['secret_key'] : '';
		$three_d_secure            = isset( $options['three_d_secure'] ) && 'yes' === $options['three_d_secure'];

		if ( isset( $options['enabled'] ) && 'yes' === $options['enabled'] ) {
			// Check if Stripe is in test mode.
			if ( $testmode ) {
				// phpcs:ignore
				$is_stripe_settings_page = isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 0 === strpos( $_GET['section'], 'stripe' );

				if ( $is_stripe_settings_page ) {
					$testmode_notice_message = sprintf(
						/* translators: 1) HTML strong open tag 2) HTML strong closing tag */
						__( '%1$sTest mode active:%2$s All transactions are simulated. Customers can\'t make real purchases through Stripe.', 'woocommerce-gateway-stripe' ),
						'<strong>',
						'</strong>'
					);

					$this->add_admin_notice( 'mode', 'notice notice-warning', $testmode_notice_message );
				}
			}

			if ( empty( $show_3ds_notice ) && $three_d_secure ) {
				$url = 'https://docs.stripe.com/payments/3d-secure/authentication-flow#three-ds-radar';

				$message = sprintf(
				/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
					__( 'WooCommerce Stripe - We see that you had the "Require 3D secure when applicable" setting turned on. This setting is not available here anymore, because it is now replaced by Stripe Radar. You can learn more about it %1$shere%2$s ', 'woocommerce-gateway-stripe' ),
					'<a href="' . $url . '" target="_blank">',
					'</a>'
				);

				$this->add_admin_notice( '3ds', 'notice notice-warning', $message, true );
			}

			if ( empty( $show_style_notice ) ) {
				$message = sprintf(
				/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
					__( 'WooCommerce Stripe - We recently made changes to Stripe that may impact the appearance of your checkout. If your checkout has changed unexpectedly, please follow these %1$sinstructions%2$s to fix.', 'woocommerce-gateway-stripe' ),
					'<a href="https://woocommerce.com/document/stripe/admin-experience/new-checkout-experience/" target="_blank">',
					'</a>'
				);

				$this->add_admin_notice( 'style', 'notice notice-warning', $message, true );

				return;
			}

			// @codeCoverageIgnoreStart
			if ( empty( $show_phpver_notice ) ) {
				if ( version_compare( phpversion(), WC_STRIPE_MIN_PHP_VER, '<' ) ) {
					/* translators: 1) int version 2) int version */
					$message = __( 'WooCommerce Stripe - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-stripe' );

					$this->add_admin_notice( 'phpver', 'error', sprintf( $message, WC_STRIPE_MIN_PHP_VER, phpversion() ), true );

					return;
				}
			}

			if ( empty( $show_wcver_notice ) ) {
				if ( WC_Stripe_Helper::is_wc_lt( WC_STRIPE_FUTURE_MIN_WC_VER ) ) {
					/* translators: 1) int version 2) int version */
					$message = __( 'WooCommerce Stripe - This is the last version of the plugin compatible with WooCommerce %1$s. All future versions of the plugin will require WooCommerce %2$s or greater.', 'woocommerce-gateway-stripe' );
					$this->add_admin_notice( 'wcver', 'notice notice-warning', sprintf( $message, WC_VERSION, WC_STRIPE_FUTURE_MIN_WC_VER ), true );
				}
			}

			if ( empty( $show_curl_notice ) ) {
				if ( ! function_exists( 'curl_init' ) ) {
					$this->add_admin_notice( 'curl', 'notice notice-warning', __( 'WooCommerce Stripe - cURL is not installed.', 'woocommerce-gateway-stripe' ), true );
				}
			}

			// @codeCoverageIgnoreEnd
			if ( empty( $show_keys_notice ) ) {
				$secret = WC_Stripe_API::get_secret_key();
				// phpcs:ignore
				$should_show_notice_on_page = ! ( isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 0 === strpos( $_GET['section'], 'stripe' ) );

				if ( empty( $secret ) && $should_show_notice_on_page ) {
					$setting_link = $this->get_setting_link();

					$notice_message = sprintf(
					/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
						__( 'Stripe is almost ready. To get started, go to %1$syour settings%2$s and use the <strong>Configure Connection</strong> button to connect.', 'woocommerce-gateway-stripe' ),
						'<a href="' . $setting_link . '">',
						'</a>'
					);
					$this->add_admin_notice( 'keys', 'notice notice-warning', $notice_message, true );
				}

				// Check if keys are entered properly per live/test mode.
				if ( $testmode ) {
					$is_test_pub_key    = ! empty( $test_pub_key ) && preg_match( '/^pk_test_/', $test_pub_key );
					$is_test_secret_key = ! empty( $test_secret_key ) && preg_match( '/^[rs]k_test_/', $test_secret_key );
					if ( ! $is_test_pub_key || ! $is_test_secret_key ) {
						$setting_link = $this->get_setting_link();

						$notice_message = sprintf(
						/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
							__( 'Stripe is in test mode however your API keys may not be valid. Please go to %1$syour settings%2$s and use the <strong>Configure Connection</strong> button to reconnect.', 'woocommerce-gateway-stripe' ),
							'<a href="' . $setting_link . '">',
							'</a>'
						);

						$this->add_admin_notice( 'keys', 'notice notice-error', $notice_message, true );
					}
				} else {
					$is_live_pub_key    = ! empty( $live_pub_key ) && preg_match( '/^pk_live_/', $live_pub_key );
					$is_live_secret_key = ! empty( $live_secret_key ) && preg_match( '/^[rs]k_live_/', $live_secret_key );
					if ( ! $is_live_pub_key || ! $is_live_secret_key ) {
						$setting_link = $this->get_setting_link();

						$message = sprintf(
						/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
							__( 'Stripe is in live mode however your API keys may not be valid. Please go to %1$syour settings%2$s and use the <strong>Configure Connection</strong> button to reconnect.', 'woocommerce-gateway-stripe' ),
							'<a href="' . $setting_link . '">',
							'</a>'
						);

						$this->add_admin_notice( 'keys', 'notice notice-error', $message, true );
					}
				}

				// Check if Stripe Account data was successfully fetched.
				$account_data = WC_Stripe::get_instance()->account->get_cached_account_data();
				if ( ! empty( $secret ) && empty( $account_data ) ) {
					$setting_link = $this->get_setting_link();

					$message = sprintf(
					/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
						__( 'Your customers cannot use Stripe on checkout, because we couldn\'t connect to your account. Please go to %1$syour settings%2$s and use the <strong>Configure Connection</strong> button to connect.', 'woocommerce-gateway-stripe' ),
						'<a href="' . $setting_link . '">',
						'</a>'
					);

					$this->add_admin_notice( 'keys', 'notice notice-error', $message, true );
				}
			}

			if ( empty( $show_ssl_notice ) ) {
				// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected.
				if ( ! wc_checkout_is_https() ) {
					$message = sprintf(
					/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
						__( 'Stripe is enabled, but a SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid %1$sSSL certificate%2$s.', 'woocommerce-gateway-stripe' ),
						'<a href="https://en.wikipedia.org/wiki/Transport_Layer_Security" target="_blank">',
						'</a>'
					);

					$this->add_admin_notice( 'ssl', 'notice notice-warning', $message, true );
				}
			}

			if ( empty( $show_sca_notice ) ) {
				$message = sprintf(
				/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
					__( 'Stripe is now ready for Strong Customer Authentication (SCA) and 3D Secure 2! %1$sRead about SCA%2$s.', 'woocommerce-gateway-stripe' ),
					'<a href="https://woocommerce.com/posts/introducing-strong-customer-authentication-sca/" target="_blank">',
					'</a>'
				);

				$this->add_admin_notice( 'sca', 'notice notice-success', $message, true );
			}

			if ( 'yes' === $changed_keys_notice ) {
				$message = sprintf(
				/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
					__( 'Credentials used for the Stripe gateway have been changed. This might cause errors for existing customers and saved payment methods. %1$sClick here to learn more%2$s.', 'woocommerce-gateway-stripe' ),
					'<a href="https://woocommerce.com/document/stripe/customization/database-cleanup/" target="_blank">',
					'</a>'
				);

				$this->add_admin_notice( 'changed_keys', 'notice notice-warning', $message, true );
			}

			if ( empty( $legacy_deprecation_notice ) ) {
				// Show legacy deprecation notice in version 9.3.0 if legacy checkout experience is enabled.
				if ( ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
					$setting_link = $this->get_setting_link();
					$message      = sprintf(
						/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
						__( 'WooCommerce Stripe Gateway legacy checkout experience has been deprecated since version 9.6.0. Please %1$smigrate to the new checkout experience%2$s to access more payment methods and avoid disruptions. %3$sLearn more%4$s', 'woocommerce-gateway-stripe' ),
						'<a href="' . $setting_link . '">',
						'</a>',
						'<a href="https://woocommerce.com/document/stripe/admin-experience/legacy-checkout-experience/" target="_blank">',
						'</a>'
					);

					$this->add_admin_notice( 'legacy_deprecation', 'notice notice-warning', $message, true );
				}
			}
		}
	}

	/**
	 * Environment check for all other payment methods.
	 *
	 * @since 4.1.0
	 */
	public function payment_methods_check_environment() {
		$payment_methods = $this->get_payment_methods();

		// phpcs:ignore
		$is_stripe_settings_page = isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 0 === strpos( $_GET['section'], 'stripe' );
		$currency_messages       = '';

		if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			foreach ( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $method_class ) {
				if ( WC_Stripe_UPE_Payment_Method_CC::class === $method_class || WC_Stripe_UPE_Payment_Method_Link::class === $method_class ) {
					continue;
				}
				$method     = $method_class::STRIPE_ID;
				$upe_method = new $method_class();
				if ( ! $upe_method->is_enabled() ) {
					continue;
				}

				if ( ! $is_stripe_settings_page && ! in_array( get_woocommerce_currency(), $upe_method->get_supported_currencies(), true ) ) {
					/* translators: %1$s Payment method, %2$s List of supported currencies */
					$currency_messages .= sprintf( __( '%1$s is enabled - it requires store currency to be set to %2$s<br>', 'woocommerce-gateway-stripe' ), $upe_method->get_label(), implode( ', ', $upe_method->get_supported_currencies() ) );
				}
			}

			$show_notice = get_option( 'wc_stripe_show_upe_payment_methods_notice' );
			if ( ! empty( $currency_messages ) && 'no' !== $show_notice ) {
				$this->add_admin_notice( 'upe_payment_methods', 'notice notice-error', $currency_messages, true );
			}
		} else {
			foreach ( $payment_methods as $method => $class ) {
				$gateway = new $class();

				if ( 'yes' !== $gateway->enabled ) {
					continue;
				}

				if ( ! $is_stripe_settings_page && ! in_array( get_woocommerce_currency(), $gateway->get_supported_currency(), true ) ) {
					/* translators: 1) Payment method, 2) List of supported currencies */
					$currency_messages .= sprintf( __( '%1$s is enabled - it requires store currency to be set to %2$s<br>', 'woocommerce-gateway-stripe' ), $gateway->get_method_title(), implode( ', ', $gateway->get_supported_currency() ) );
				}
			}

			$show_notice = get_option( 'wc_stripe_show_payment_methods_notice' );
			if ( ! empty( $currency_messages && 'no' !== $show_notice ) ) {
				$this->add_admin_notice( 'payment_methods', 'notice notice-error', $currency_messages, true );
			}
		}
	}

	/**
	 * Adds a notice to the subscription details page if we are looking at an active subscription and the payment method has been detached.
	 *
	 * @return void
	 */
	public function subscription_check_detachment() {
		if ( ! self::is_subscription_edit_page() ) {
			return;
		}

		global $theorder;

		$subscription = null;

		if ( isset( $theorder ) ) {
			$subscription = $theorder;
		} elseif ( ! empty( $GLOBALS['post']->ID ) ) { // If $theorder is empty (i.e. non-HPOS), fallback to using the global post object.
			$subscription = wcs_get_subscription( $GLOBALS['post']->ID );
		}

		if ( ! isset( $subscription ) || ! $subscription instanceof WC_Subscription ) {
			return;
		}

		if ( ! $subscription->has_status( [ 'active' ] ) ) {
			// Only show the notice for active subscriptions.
			return;
		}

		if ( WC_Stripe_Subscriptions_Helper::is_subscription_payment_method_detached( $subscription ) ) {
			$customer_payment_method_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $subscription->get_change_payment_method_url() ),
				esc_html(
					/* translators: this is a text for a link pointing to the customer's payment method page */
					__( 'Payment method page &rarr;', 'woocommerce-gateway-stripe' )
				)
			);
			$customer_stripe_page = sprintf(
				'<a href="%s">%s</a>',
				esc_url( WC_Stripe_Subscriptions_Helper::STRIPE_CUSTOMER_PAGE_BASE_URL . $subscription->get_meta( '_stripe_customer_id' ) ),
				esc_html(
					/* translators: this is a text for a link pointing to the customer's page on Stripe */
					__( 'Stripe customer page &rarr;', 'woocommerce-gateway-stripe' )
				)
			);

			$detached_message  = __( 'The payment method for this subscription has been detached, <strong>preventing renewals</strong>. ', 'woocommerce-gateway-stripe' );
			$detached_message .= __( 'To fix this, either: <br />', 'woocommerce-gateway-stripe' );
			$detached_message .= __( '1) Share the payment method page link with the customer to update it: ', 'woocommerce-gateway-stripe' ) . $customer_payment_method_link . '<br />';
			$detached_message .= __( ' or <br />', 'woocommerce-gateway-stripe' );
			$detached_message .= __( "2) Manually update the payment method in the subscription's billing details using a valid payment method from the customer's Stripe account: ", 'woocommerce-gateway-stripe' ) . $customer_stripe_page . '<br />';
			$detached_message .= '<br />' . sprintf(
				/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag 3) The already-translated title of the tool*/
				__( 'To list all your current subscriptions with payment methods detached, go to WooCommerce -> Status -> %1$sTools%2$s -> <strong>%3$s</strong>.', 'woocommerce-gateway-stripe' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=tools' ) ) . '">',
				'</a>',
				__( 'List Stripe subscriptions with detached payment method', 'woocommerce-gateway-stripe' ),
			);
			$this->add_admin_notice( 'subscription_detached', 'notice notice-error', $detached_message );
		}
	}

	/**
	 * Add a notice to the admin area if there are subscriptions with payment method detached.
	 *
	 * @return void
	 */
	public function subscription_check_detachment_bulk_action() {
		if ( isset( $_REQUEST['detached-subscriptions'] ) && 'no' !== get_option( 'wc_stripe_show_subscription_detached_bulk_action_notice' ) ) {
			$notice_content = '<p>' . esc_html__( 'No detached subscriptions found.', 'woocommerce-gateway-stripe' ) . '</p>';
			$notice_class   = 'info';
			if ( ! empty( $_REQUEST['detached-subscriptions'] ) ) {
				$detached_subs_ids = explode( ',', sanitize_text_field( wp_unslash( $_REQUEST['detached-subscriptions'] ) ) );
				$subscriptions     = [];
				foreach ( $detached_subs_ids as $detached_sub_id ) {
					$detached_sub_id = absint( $detached_sub_id );
					$subscription    = wcs_get_subscription( $detached_sub_id );
					if ( ! $subscription instanceof WC_Subscription ) {
						continue;
					}
					$subscriptions[] = WC_Stripe_Subscriptions_Helper::get_detached_payment_data_from_subscription( $subscription );
				}
				$detached_messages = WC_Stripe_Subscriptions_Helper::build_subscriptions_detached_messages( $subscriptions );
				if ( ! empty( $detached_messages ) ) {
					$notice_content = '<p>';
					$notice_content .= wp_kses(
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
					$notice_content .= '</p>';
					$notice_class    = 'error';
				}
			}
			$this->add_admin_notice( 'subscription_detached_bulk_action', 'notice notice-' . $notice_class, $notice_content, true );
		}
	}

	/**
	 * Environment check for subscriptions.
	 *
	 * @return void
	 *
	 * @deprecated 9.6.0 This method is no longer used and will be removed in a future version.
	 */
	public function subscriptions_check_environment() {
		_deprecated_function( __METHOD__, '9.6.0' );
		$options = WC_Stripe_Helper::get_stripe_settings();
		if ( 'yes' === ( $options['enabled'] ?? null ) && 'no' !== get_option( 'wc_stripe_show_subscriptions_notice' ) ) {
			$subscriptions     = WC_Stripe_Subscriptions_Helper::get_some_detached_subscriptions();
			$detached_messages = WC_Stripe_Subscriptions_Helper::build_subscriptions_detached_messages( $subscriptions );
			if ( ! empty( $detached_messages ) ) {
				$this->add_admin_notice( 'subscriptions', 'notice notice-error', $detached_messages, true );
			}
		}
	}

	/**
	 * Hides any admin notices.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function hide_notices() {
		if ( isset( $_GET['wc-stripe-hide-notice'] ) && isset( $_GET['_wc_stripe_notice_nonce'] ) ) {
			if ( ! wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wc_stripe_notice_nonce'] ) ), 'wc_stripe_hide_notices_nonce' ) ) {
				wp_die( esc_html__( 'Action failed. Please refresh the page and retry.', 'woocommerce-gateway-stripe' ) );
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'Cheatin&#8217; huh?', 'woocommerce-gateway-stripe' ) );
			}

			$notice = wc_clean( wp_unslash( $_GET['wc-stripe-hide-notice'] ) );

			switch ( $notice ) {
				case 'style':
					update_option( 'wc_stripe_show_style_notice', 'no' );
					break;
				case 'phpver':
					update_option( 'wc_stripe_show_phpver_notice', 'no' );
					break;
				case 'wcver':
					update_option( 'wc_stripe_show_wcver_notice', 'no' );
					break;
				case 'curl':
					update_option( 'wc_stripe_show_curl_notice', 'no' );
					break;
				case 'ssl':
					update_option( 'wc_stripe_show_ssl_notice', 'no' );
					break;
				case 'keys':
					update_option( 'wc_stripe_show_keys_notice', 'no' );
					break;
				case '3ds':
					update_option( 'wc_stripe_show_3ds_notice', 'no' );
					break;
				case 'sofort':
					update_option( 'wc_stripe_show_sofort_notice', 'no' );
					update_option( 'wc_stripe_show_sofort_upe_notice', 'no' );
					break;
				case 'sca':
					update_option( 'wc_stripe_show_sca_notice', 'no' );
					break;
				case 'changed_keys':
					update_option( 'wc_stripe_show_changed_keys_notice', 'no' );
					break;
				case 'legacy_deprecation':
					update_option( 'wc_stripe_show_legacy_deprecation_notice', 'no' );
					break;
				case 'payment_methods':
					update_option( 'wc_stripe_show_payment_methods_notice', 'no' );
					break;
				case 'upe_payment_methods':
					update_option( 'wc_stripe_show_upe_payment_methods_notice', 'no' );
					break;
				case 'oauth_required':
					update_option( 'wc_stripe_show_oauth_required_notice', 'no' );
					break;
				case 'subscriptions':
					update_option( 'wc_stripe_show_subscriptions_notice', 'no' );
					break;
				case 'subscription_detached_bulk_action':
					update_option( 'wc_stripe_show_subscription_detached_bulk_action_notice', 'no' );

					// Redirect back to the current page without the query param to hide the notice to avoid issues.
					if ( isset( $_SERVER['REQUEST_URI'] ) ) {
						wp_safe_redirect( remove_query_arg( [ 'wc-stripe-hide-notice', '_wc_stripe_notice_nonce' ], esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
					}
					break;
			}
		}
	}

	/**
	 * Get setting link.
	 *
	 * @since 1.0.0
	 *
	 * @return string Setting link
	 */
	public function get_setting_link() {
		return esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings' ) );
	}

	/**
	 * Saves options in order to hide notices based on the gateway's version.
	 *
	 * @since 4.3.0
	 */
	public function stripe_updated() {
		$previous_version = get_option( 'wc_stripe_version' );

		// Only show the style notice if the plugin was installed and older than 4.1.4.
		if ( empty( $previous_version ) || version_compare( $previous_version, '4.1.4', 'ge' ) ) {
			update_option( 'wc_stripe_show_style_notice', 'no' );
		}

		// Only show the SCA notice on pre-4.3.0 installs.
		if ( empty( $previous_version ) || version_compare( $previous_version, '4.3.0', 'ge' ) ) {
			update_option( 'wc_stripe_show_sca_notice', 'no' );
		}
	}

	/**
	 * Checks if the current page is a subscription edit page in wp-admin.
	 *
	 * This should be removed once WooCommerce provides a way to check for subscription edit pages.
	 *
	 * @return bool
	 */
	private static function is_subscription_edit_page() {
		$query_params = wp_unslash( $_REQUEST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( WC_Stripe_Woo_Compat_Utils::is_custom_orders_table_enabled() ) { // If custom order tables are enabled, we need to check the page query param.
			return isset( $query_params['page'] ) && 'wc-orders--shop_subscription' === $query_params['page'] && isset( $query_params['id'] );
		}

		// If custom order tables are not enabled, we need to check the post type and action query params.
		$is_shop_subscription_post_type = isset( $query_params['post'] ) && 'shop_subscription' === get_post_type( $query_params['post'] );
		return isset( $query_params['action'] ) && 'edit' === $query_params['action'] && $is_shop_subscription_post_type;
	}
}

new WC_Stripe_Admin_Notices();
