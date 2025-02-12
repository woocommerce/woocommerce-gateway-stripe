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
					<h2>WooCommerce Stripe Payment Gateway</h2>
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
				<td><?php echo esc_html( $account_data['email'] ?? '' ); ?></td>
			</tr>
			<tr>
				<td data-export-label="Test Mode"><?php esc_html_e( 'Test Mode', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the payment gateway has test payments enabled.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td><?php WC_Stripe_Mode::is_test() ? esc_html_e( 'Enabled', 'woocommerce-gateway-stripe' ) : esc_html_e( 'Disabled', 'woocommerce-gateway-stripe' ); ?></td>
			</tr>
			<tr>
				<td data-export-label="OAuth Connected"><?php esc_html_e( 'OAuth Connected', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the Stripe account is connected via OAuth.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td>
				<?php
					$stripe_connect = woocommerce_gateway_stripe()->connect;
					$mode           = WC_Stripe_Mode::is_test() ? 'test' : 'live';
					(bool) $stripe_connect->is_connected_via_oauth( $mode ) ? esc_html_e( 'Yes', 'woocommerce-gateway-stripe' ) : esc_html_e( 'No', 'woocommerce-gateway-stripe' );
				?>
				</td>
			</tr>
			<tr>
				<td data-export-label="Legacy Checkout Experience"><?php esc_html_e( 'Legacy Checkout Experience', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the payment gateway has the legacy checkout experience enabled.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td><?php WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ? esc_html_e( 'Disabled', 'woocommerce-gateway-stripe' ) : esc_html_e( 'Enabled', 'woocommerce-gateway-stripe' ); ?></td>
			</tr>
			<tr>
				<td data-export-label="Enabled Payment Methods"><?php esc_html_e( 'Enabled Payment Methods', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'What payment methods are enabled for the store.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td><?php echo esc_html( implode( ',', $this->gateway->get_upe_enabled_payment_method_ids() ) ); ?></td>
			</tr>
			<?php if ( ! WC_Stripe_Feature_Flags::is_stripe_ece_enabled() || ! $express_checkout_helper->is_express_checkout_enabled() ) : ?>
			<tr>
				<td data-export-label="Express Checkout"><?php esc_html_e( 'Express Checkout', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help">
					<?php
					echo wc_help_tip( esc_html__( 'Express Checkout is not enabled.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */
					?>
				</td>
			</tr>
		<?php else : ?>
			<tr>
				<td data-export-label="Express Checkout"><?php esc_html_e( 'Express Checkout', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether Express Checkout is enabled.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td>
					<?php
					$express_checkout_enabled_locations = $express_checkout_helper->get_button_locations();
					$express_checkout_enabled_locations = empty( $express_checkout_enabled_locations ) ? 'no locations enabled' : implode( ',', $express_checkout_enabled_locations );
					echo __( 'Enabled', 'woocommerce-gateway-stripe' ) . ' (' . $express_checkout_enabled_locations . ')';
					?>
				</td>
			</tr>
		<?php endif; ?>
			<tr>
				<td data-export-label="Auth and Capture"><?php esc_html_e( 'Auth and Capture', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether the store has the Auth & Capture feature enabled.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td>
					<?php
					echo $this->gateway->is_automatic_capture_enabled() ? esc_html_e( 'Enabled', 'woocommerce-gateway-stripe' ) : esc_html_e( 'Disabled', 'woocommerce-gateway-stripe' );
					?>
				</td>
			</tr>
			<tr>
				<td data-export-label="Logging"><?php esc_html_e( 'Logging', 'woocommerce-gateway-stripe' ); ?>:</td>
				<td class="help"><?php echo wc_help_tip( esc_html__( 'Whether debug logging is enabled and working or not.', 'woocommerce-gateway-stripe' ) ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.OutputNotEscaped */ ?></td>
				<td><?php WC_Stripe_Logger::can_log() ? esc_html_e( 'Enabled', 'woocommerce-gateway-stripe' ) : esc_html_e( 'Disabled', 'woocommerce-gateway-stripe' ); ?></td>
			</tr>
			</tbody>
		</table>
		<?php
	}
}
