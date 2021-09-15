<?php
/**
 * Class WC_REST_Stripe_Settings_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for settings.
 */
class WC_REST_Stripe_Settings_Controller extends WC_Stripe_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/settings';

	/**
	 * Stripe payment gateway.
	 *
	 * @var WC_Stripe_Payment_Gateway
	 */
	private $gateway;

	/**
	 * Constructor.
	 *
	 * Injected gateway should be an instance of WC_Gateway_Stripe or WC_Stripe_UPE_Payment_Gateway.
	 *
	 * @param WC_Stripe_Payment_Gateway $gateway Stripe payment gateway.
	 */
	public function __construct( WC_Stripe_Payment_Gateway $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		$wcpay_form_fields = $this->gateway->get_form_fields();

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'is_stripe_enabled'                 => [
						'description'       => __( 'If Stripe should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_test_mode_enabled'              => [
						'description'       => __( 'Stripe test mode setting.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_payment_request_enabled'        => [
						'description'       => __( 'If Stripe express checkouts should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_enabled_locations' => [
						'description'       => __( 'Express checkout locations that should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $wcpay_form_fields['payment_request_button_locations']['options'] ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
	}

	/**
	 * Retrieve settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		return new WP_REST_Response(
			[
				/* Settings > General */
				'is_stripe_enabled'    => $this->gateway->is_enabled(),
				'is_test_mode_enabled' => $this->gateway->is_in_test_mode(),

				/* Settings > Express checkouts */
				'is_payment_request_enabled'        => 'yes' === $this->gateway->get_option( 'payment_request' ),
				'payment_request_enabled_locations' => $this->gateway->get_option( 'payment_request_button_locations' ),

				/* Settings > Payments & transactions */
				'is_manual_capture_enabled'             => 'no' === $this->gateway->get_option( 'capture' ),
				'is_saved_cards_enabled'                => 'yes' === $this->gateway->get_option( 'saved_cards' ),
				'is_separate_card_form_enabled'         => 'no' === $this->gateway->get_option( 'inline_cc_form' ),
				'statement_descriptor'                  => $this->gateway->get_option( 'statement_descriptor' ),
				'is_short_statement_descriptor_enabled' => 'yes' === $this->gateway->get_option( 'is_short_statement_descriptor_enabled' ),
				'short_statement_descriptor'            => $this->gateway->get_option( 'short_statement_descriptor' ),

				/* Settings > Advanced settings */
				'is_debug_log_enabled' => 'yes' === $this->gateway->get_option( 'logging' ),
			]
		);
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function update_settings( WP_REST_Request $request ) {
		/* Settings > General */
		$this->update_is_stripe_enabled( $request );
		$this->update_is_test_mode_enabled( $request );

		/* Settings > Express checkouts */
		$this->update_is_payment_request_enabled( $request );
		$this->update_payment_request_enabled_locations( $request );

		/* Settings > Payments & transactions */
		$this->update_is_manual_capture_enabled( $request );
		$this->update_is_saved_cards_enabled( $request );
		$this->update_is_separate_card_form_enabled( $request );
		$this->update_account_statement_descriptor( $request );
		$this->update_is_short_account_statement_enabled( $request );
		$this->update_short_account_statement_descriptor( $request );

		/* Settings > Advanced settings */
		$this->update_is_debug_log_enabled( $request );

		return new WP_REST_Response( [], 200 );
	}

	/**
	 * Updates Stripe enabled status.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_stripe_enabled( WP_REST_Request $request ) {
		$is_stripe_enabled = $request->get_param( 'is_stripe_enabled' );

		if ( null === $is_stripe_enabled ) {
			return;
		}

		if ( $is_stripe_enabled ) {
			$this->gateway->enable();
		} else {
			$this->gateway->disable();
		}
	}

	/**
	 * Updates Stripe test mode.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_test_mode_enabled( WP_REST_Request $request ) {
		$is_test_mode_enabled = $request->get_param( 'is_test_mode_enabled' );

		if ( null === $is_test_mode_enabled ) {
			return;
		}

		$this->gateway->update_option( 'testmode', $is_test_mode_enabled ? 'yes' : 'no' );
	}

	/**
	 * Updates the "payment request" enable/disable settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_payment_request_enabled( WP_REST_Request $request ) {
		$is_payment_request_enabled = $request->get_param( 'is_payment_request_enabled' );

		if ( null === $is_payment_request_enabled ) {
			return;
		}

		$this->gateway->update_option( 'payment_request', $is_payment_request_enabled ? 'yes' : 'no' );
	}

	/**
	 * Updates the list of locations that will show the payment request button.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_payment_request_enabled_locations( WP_REST_Request $request ) {
		$payment_request_enabled_locations = $request->get_param( 'payment_request_enabled_locations' );

		if ( null === $payment_request_enabled_locations ) {
			return;
		}

		$this->gateway->update_option( 'payment_request_button_locations', $payment_request_enabled_locations );
	}

	/**
	 * Updates WooCommerce Payments manual capture.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_manual_capture_enabled( WP_REST_Request $request ) {
		$is_manual_capture_enabled = $request->get_param( 'is_manual_capture_enabled' );

		if ( null === $is_manual_capture_enabled ) {
			return;
		}

		$this->gateway->update_option( 'capture', $is_manual_capture_enabled ? 'no' : 'yes' );
	}

	/**
	 * Updates WooCommerce Payments "saved cards" feature.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_saved_cards_enabled( WP_REST_Request $request ) {
		$is_saved_cards_enabled = $request->get_param( 'is_saved_cards_enabled' );

		if ( null === $is_saved_cards_enabled ) {
			return;
		}

		$this->gateway->update_option( 'saved_cards', $is_saved_cards_enabled ? 'yes' : 'no' );
	}

	/**
	 * Updates WooCommerce Payments "saved cards" feature.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_separate_card_form_enabled( WP_REST_Request $request ) {
		$is_separate_card_form_enabled = $request->get_param( 'is_separate_card_form_enabled' );

		if ( null === $is_separate_card_form_enabled ) {
			return;
		}

		$this->gateway->update_option( 'inline_cc_form', $is_separate_card_form_enabled ? 'no' : 'yes' );
	}

	/**
	 * Updates WooCommerce Payments account statement descriptor.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_account_statement_descriptor( WP_REST_Request $request ) {
		$account_statement_descriptor = $request->get_param( 'statement_descriptor' );

		if ( null === $account_statement_descriptor ) {
			return;
		}

		$this->gateway->update_option( 'statement_descriptor', $account_statement_descriptor );
	}

	/**
	 * Updates WooCommerce Payments manual capture.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_short_account_statement_enabled( WP_REST_Request $request ) {
		$is_short_account_statement_enabled = $request->get_param( 'is_short_statement_descriptor_enabled' );

		if ( null === $is_short_account_statement_enabled ) {
			return;
		}

		$this->gateway->update_option( 'is_short_statement_descriptor_enabled', $is_short_account_statement_enabled ? 'yes' : 'no' );
	}

	/**
	 * Updates WooCommerce Payments account statement descriptor.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_short_account_statement_descriptor( WP_REST_Request $request ) {
		$short_account_statement_descriptor = $request->get_param( 'short_statement_descriptor' );

		if ( null === $short_account_statement_descriptor ) {
			return;
		}

		$this->gateway->update_option( 'short_statement_descriptor', $short_account_statement_descriptor );
	}

	/**
	 * Updates WooCommerce Payments test mode.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_debug_log_enabled( WP_REST_Request $request ) {
		$is_debug_log_enabled = $request->get_param( 'is_debug_log_enabled' );

		if ( null === $request->has_param( 'is_debug_log_enabled' ) ) {
			return;
		}

		$this->gateway->update_option( 'logging', $is_debug_log_enabled ? 'yes' : 'no' );
	}
}
