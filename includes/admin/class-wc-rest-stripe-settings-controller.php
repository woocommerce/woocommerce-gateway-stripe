<?php
/**
 * Class WC_REST_Stripe_Settings_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for settings.
 */
class WC_REST_Stripe_Settings_Controller extends WC_Stripe_REST_Base_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/settings';

	/**
	 * Stripe payment gateway.
	 *
	 * @var WC_Gateway_Stripe
	 */
	private $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_Gateway_Stripe $gateway Stripe payment gateway.
	 */
	public function __construct( WC_Gateway_Stripe $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		$form_fields = $this->gateway->get_form_fields();

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
					'is_stripe_enabled'                  => [
						'description'       => __( 'If Stripe should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_test_mode_enabled'               => [
						'description'       => __( 'Stripe test mode setting.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'title_upe'                        => [
						'description'       => __( 'New checkout experience title.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'enabled_payment_method_ids'       => [
						'description'       => __( 'Payment method IDs that should be enabled. Other methods will be disabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => array_merge( WC_Stripe_Helper::get_upe_settings_available_payment_method_ids( $this->gateway ), WC_Stripe_Helper::get_legacy_available_payment_method_ids() ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'individual_payment_method_settings' => [
						'description'       => __( 'Individual payment method settings.', 'woocommerce-gateway-stripe' ),
						'type'              => 'object',
						'items'             => [
							'type' => 'object',
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_payment_request_enabled'         => [
						'description'       => __( 'If Stripe express checkouts should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_type'        => [
						'description'       => __( 'Express checkout button types.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'enum'              => array_keys( $form_fields['payment_request_button_type']['options'] ),
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_theme'       => [
						'description'       => __( 'Express checkout button themes.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'enum'              => array_keys( $form_fields['payment_request_button_theme']['options'] ),
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_size'        => [
						'description'       => __( 'Express checkout button sizes.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						// it can happen that `$form_fields['payment_request_button_size']` is empty (in tests) - fixing temporarily.
						'enum'              => array_keys( isset( $form_fields['payment_request_button_size']['options'] ) ? $form_fields['payment_request_button_size']['options'] : [] ),
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_locations'   => [
						'description'       => __( 'Express checkout locations that should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $form_fields['payment_request_button_locations']['options'] ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_manual_capture_enabled'          => [
						'description'       => __( 'If manual capture of charges should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_saved_cards_enabled'             => [
						'description'       => __( 'If "Saved cards" should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_separate_card_form_enabled'      => [
						'description'       => __( 'If credit card number field, expiry date field, and CVC field should be separate.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_short_statement_descriptor_enabled' => [
						'description'       => __( 'When enabled, we\'ll include the order number for card and express checkout transactions.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_debug_log_enabled'               => [
						'description'       => __( 'When enabled, payment error logs will be saved to WooCommerce > Status > Logs.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/payment_method_order',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_payment_methods_order' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'ordered_payment_method_ids' => [
						'description'       => __( 'The order for the payment method IDs to be saved.', 'woocommerce-gateway-stripe' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => WC_Stripe_Helper::get_legacy_available_payment_method_ids(),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/payment_method',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_individual_payment_method_settings' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'payment_method' => [
						'description'       => __( 'Payment method id.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'enum'              => WC_Stripe_Helper::get_legacy_available_payment_method_ids(),
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_enabled'     => [
						'description'       => __( 'If payment method should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'title'          => [
						'description'       => __( 'Payment method title.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'description'    => [
						'description'       => __( 'Payment method description.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/notice',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'dismiss_customization_notice' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		$is_upe_enabled               = WC_Stripe_Feature_Flags::is_upe_checkout_enabled();
		$available_payment_method_ids = $is_upe_enabled ? WC_Stripe_Helper::get_upe_settings_available_payment_method_ids( $this->gateway ) : WC_Stripe_Helper::get_legacy_available_payment_method_ids();

		return new WP_REST_Response(
			[
				/* Settings > General */
				'is_stripe_enabled'                     => $this->gateway->is_enabled(),
				'is_test_mode_enabled'                  => $this->gateway->is_in_test_mode(),
				'title_upe'                             => $this->gateway->get_validated_option( 'title_upe' ),

				/* Settings > Payments accepted on checkout */
				'enabled_payment_method_ids'            => $is_upe_enabled ? WC_Stripe_Helper::get_upe_settings_enabled_payment_method_ids( $this->gateway ) : WC_Stripe_Helper::get_legacy_enabled_payment_method_ids(),
				'available_payment_method_ids'          => $available_payment_method_ids,
				'ordered_payment_method_ids'            => array_values( array_diff( $available_payment_method_ids, [ 'link' ] ) ), // exclude Link from this list as it is a express methods.
				'individual_payment_method_settings'    => $is_upe_enabled ? [] : WC_Stripe_Helper::get_legacy_individual_payment_method_settings(),

				/* Settings > Express checkouts */
				'is_payment_request_enabled'            => 'yes' === $this->gateway->get_option( 'payment_request' ),
				'payment_request_button_type'           => $this->gateway->get_validated_option( 'payment_request_button_type' ),
				'payment_request_button_theme'          => $this->gateway->get_validated_option( 'payment_request_button_theme' ),
				'payment_request_button_size'           => $this->gateway->get_validated_option( 'payment_request_button_size' ),
				'payment_request_button_locations'      => $this->gateway->get_validated_option( 'payment_request_button_locations' ),

				/* Settings > Payments & transactions */
				'is_manual_capture_enabled'             => ! $this->gateway->is_automatic_capture_enabled(),
				'is_saved_cards_enabled'                => 'yes' === $this->gateway->get_option( 'saved_cards' ),
				'is_separate_card_form_enabled'         => 'no' === $this->gateway->get_option( 'inline_cc_form' ),
				'statement_descriptor'                  => $this->gateway->get_validated_option( 'statement_descriptor' ),
				'is_short_statement_descriptor_enabled' => 'yes' === $this->gateway->get_option( 'is_short_statement_descriptor_enabled' ),
				'short_statement_descriptor'            => $this->gateway->get_validated_option( 'short_statement_descriptor' ),

				/* Settings > Advanced settings */
				'is_debug_log_enabled'                  => 'yes' === $this->gateway->get_option( 'logging' ),
				'is_upe_enabled'                        => $is_upe_enabled,
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
		$this->update_title_upe( $request );
		$this->update_is_test_mode_enabled( $request );

		/* Settings > Payments accepted on checkout */
		$this->update_enabled_payment_methods( $request );
		$this->update_individual_payment_method_settings( $request );

		/* Settings > Express checkouts */
		$this->update_is_payment_request_enabled( $request );
		$this->update_payment_request_settings( $request );

		/* Settings > Payments & transactions */
		$this->update_is_manual_capture_enabled( $request );
		$this->update_is_saved_cards_enabled( $request );
		$this->update_is_separate_card_form_enabled( $request );
		$this->update_is_short_account_statement_enabled( $request );

		/* Settings > Advanced settings */
		$this->update_is_debug_log_enabled( $request );
		$this->update_is_upe_enabled( $request );

		return new WP_REST_Response( [], 200 );
	}

	/**
	 * Updates the order of available payment methods in settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function update_payment_methods_order( WP_REST_Request $request ) {
		$ordered_payment_method_ids = $request->get_param( 'ordered_payment_method_ids' );

		if ( empty( $ordered_payment_method_ids ) ) {
			return new WP_REST_Response( [], 403 );
		}

		$ordered_payment_method_ids = array_map(
			function ( $id ) {
				if ( 'card' === $id ) {
					return 'stripe';
				}
				return 'stripe_' . $id;
			},
			$ordered_payment_method_ids
		);

		$this->gateway->update_option( 'stripe_legacy_method_order', $ordered_payment_method_ids );

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
	 * Updates UPE title.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_title_upe( WP_REST_Request $request ) {
		$title_upe = $request->get_param( 'title_upe' );

		if ( null === $title_upe ) {
			return;
		}

		$this->gateway->update_validated_option( 'title_upe', $title_upe );
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
	 * Updates manual capture.
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
	 * Updates "saved cards" feature.
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
	 * Updates "saved cards" feature.
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
	 * Updates whether short account statement should be used.
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
	 * Updates whether debug logging is enabled.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_debug_log_enabled( WP_REST_Request $request ) {
		$is_debug_log_enabled = $request->get_param( 'is_debug_log_enabled' );

		if ( null === $is_debug_log_enabled ) {
			return;
		}

		$this->gateway->update_option( 'logging', $is_debug_log_enabled ? 'yes' : 'no' );

	}

	/**
	 * Updates whether debug logging is enabled.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_upe_enabled( WP_REST_Request $request ) {
		$is_upe_enabled = $request->get_param( 'is_upe_enabled' );

		if ( null === $is_upe_enabled ) {
			return;
		}

		$settings = get_option( 'woocommerce_stripe_settings', [] );
		$settings[ WC_Stripe_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] = $is_upe_enabled ? 'yes' : 'disabled';
		update_option( 'woocommerce_stripe_settings', $settings );

		// including the class again because otherwise it's not present.
		if ( WC_Stripe_Inbox_Notes::are_inbox_notes_supported() ) {
			require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-upe-availability-note.php';
			WC_Stripe_UPE_Availability_Note::possibly_delete_note();

			require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-upe-stripelink-note.php';
			WC_Stripe_UPE_StripeLink_Note::possibly_delete_note();
		}
	}

	/**
	 * Updates appearance attributes of the payment request button.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_payment_request_settings( WP_REST_Request $request ) {
		$attributes = [
			'payment_request_button_type'      => 'payment_request_button_type',
			'payment_request_button_size'      => 'payment_request_button_size',
			'payment_request_button_theme'     => 'payment_request_button_theme',
			'payment_request_button_locations' => 'payment_request_button_locations',
		];

		foreach ( $attributes as $request_key => $attribute ) {
			if ( null === $request->get_param( $request_key ) ) {
				continue;
			}

			$value = $request->get_param( $request_key );
			$this->gateway->update_validated_option( $attribute, $value );
		}
	}

	/**
	 * Updates the list of enabled payment methods.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_enabled_payment_methods( WP_REST_Request $request ) {
		$payment_method_ids_to_enable = $request->get_param( 'enabled_payment_method_ids' );
		$is_upe_enabled               = $request->get_param( 'is_upe_enabled' );

		if ( null === $is_upe_enabled ) {
			return;
		}

		if ( ! $is_upe_enabled ) {
			$currently_enabled_payment_method_ids = WC_Stripe_Helper::get_legacy_enabled_payment_method_ids();
			$payment_gateways                     = WC_Stripe_Helper::get_legacy_payment_methods();

			foreach ( $payment_gateways as $gateway ) {
				$gateway_id = str_replace( 'stripe_', '', $gateway->id );
				if ( ! in_array( $gateway_id, $payment_method_ids_to_enable, true ) && in_array( $gateway_id, $currently_enabled_payment_method_ids, true ) ) {
					$gateway->update_option( 'enabled', 'no' );
				} elseif ( in_array( $gateway_id, $payment_method_ids_to_enable, true ) ) {
					$gateway->update_option( 'enabled', 'yes' );
				}
			}

			return;
		}

		if ( null === $payment_method_ids_to_enable ) {
			return;
		}

		$upe_checkout_experience_accepted_payments = [];

		foreach ( WC_Stripe_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $gateway ) {
			if ( in_array( $gateway::STRIPE_ID, $payment_method_ids_to_enable, true ) ) {
				$upe_checkout_experience_accepted_payments[] = $gateway::STRIPE_ID;
			}
		}

		$this->gateway->update_option( 'upe_checkout_experience_accepted_payments', $upe_checkout_experience_accepted_payments );

		// handle Multibanco separately as it is a non UPE method but it is part of the same settings page.
		$multibanco            = WC_Stripe_Helper::get_legacy_payment_method( 'stripe_multibanco' );
		$is_multibanco_enabled = $multibanco->is_enabled();
		if ( in_array( 'multibanco', $payment_method_ids_to_enable, true ) && ! $is_multibanco_enabled ) {
			$multibanco->update_option( 'enabled', 'yes' );
		} elseif ( ! in_array( 'multibanco', $payment_method_ids_to_enable, true ) && $is_multibanco_enabled ) {
			$multibanco->update_option( 'enabled', 'no' );
		}
	}

	/**
	 * Update individual payment gateway settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function update_individual_payment_method_settings( WP_REST_Request $request ) {
		$payment_method_id = $request->get_param( 'payment_method_id' );
		// Map the ids used in the frontend to the legacy gateway class ids.
		$mapped_legacy_method_id = ( 'stripe_' . $payment_method_id );
		$is_enabled              = $request->get_param( 'is_enabled' );
		$title                   = sanitize_text_field( $request->get_param( 'title' ) );
		$description             = sanitize_text_field( $request->get_param( 'description' ) );

		// In legacy mode (when UPE is disabled), Stripe gateway refers to card as payment method id.
		if ( 'card' === $payment_method_id ) {
			$this->gateway->update_option( 'title', $title );
			$this->gateway->update_option( 'description', $description );
			return new WP_REST_Response( [], 200 );
		}

		$payment_gateway = WC_Stripe_Helper::get_legacy_payment_method( $mapped_legacy_method_id );

		if ( ! $payment_gateway ) {
			return new WP_REST_Response( [ 'result' => 'payment method not found' ], 404 );
		}

		$payment_gateway->update_option( 'title', $title );
		$payment_gateway->update_option( 'description', $description );

		if ( $request->get_param( 'expiration' ) && method_exists( $payment_gateway, 'update_unique_settings' ) ) {
			$request->set_param( $mapped_legacy_method_id . '_expiration', $request->get_param( 'expiration' ) );
			$payment_gateway->update_unique_settings( $request );
		}

		return new WP_REST_Response( [], 200 );
	}

	/**
	 * Set `wc_stripe_show_customization_notice` as `no` to dismiss notice.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function dismiss_customization_notice( WP_REST_Request $request ) {
		if ( null === $request->get_param( 'wc_stripe_show_customization_notice' ) ) {
			return new WP_REST_Response( [], 200 );
		}

		update_option( 'wc_stripe_show_customization_notice', 'no' );
		return new WP_REST_Response( [ 'result' => 'notice dismissed' ], 200 );
	}
}
