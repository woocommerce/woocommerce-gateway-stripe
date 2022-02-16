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
					'is_stripe_enabled'                => [
						'description'       => __( 'If Stripe should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_test_mode_enabled'             => [
						'description'       => __( 'Stripe test mode setting.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'title'                            => [
						'description'       => __( 'Stripe display title.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'title_upe'                        => [
						'description'       => __( 'New checkout experience title.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'description'                      => [
						'description'       => __( 'Stripe display description.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'enabled_payment_method_ids'       => [
						'description'       => __( 'Payment method IDs that should be enabled. Other methods will be disabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => $this->gateway->get_upe_available_payment_methods(),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_payment_request_enabled'       => [
						'description'       => __( 'If Stripe express checkouts should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_type'      => [
						'description'       => __( 'Express checkout button types.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'enum'              => array_keys( $form_fields['payment_request_button_type']['options'] ),
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_theme'     => [
						'description'       => __( 'Express checkout button themes.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'enum'              => array_keys( $form_fields['payment_request_button_theme']['options'] ),
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_size'      => [
						'description'       => __( 'Express checkout button sizes.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						// it can happen that `$form_fields['payment_request_button_size']` is empty (in tests) - fixing temporarily.
						'enum'              => array_keys( isset( $form_fields['payment_request_button_size']['options'] ) ? $form_fields['payment_request_button_size']['options'] : [] ),
						'validate_callback' => 'rest_validate_request_arg',
					],
					'payment_request_button_locations' => [
						'description'       => __( 'Express checkout locations that should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $form_fields['payment_request_button_locations']['options'] ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_manual_capture_enabled'        => [
						'description'       => __( 'If manual capture of charges should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_saved_cards_enabled'           => [
						'description'       => __( 'If "Saved cards" should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_separate_card_form_enabled'    => [
						'description'       => __( 'If credit card number field, expiry date field, and CVC field should be separate.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'statement_descriptor'             => [
						'description'       => __( 'Bank account descriptor to be displayed in customers\' bank accounts.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_regular_statement_descriptor' ],
					],
					'is_short_statement_descriptor_enabled' => [
						'description'       => __( 'When enabled, we\'ll include the order number for card and express checkout transactions.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'short_statement_descriptor'       => [
						'description'       => __( 'We\'ll use the short version in combination with the customer order number.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_short_statement_descriptor' ],
					],
					'is_debug_log_enabled'             => [
						'description'       => __( 'When enabled, payment error logs will be saved to WooCommerce > Status > Logs.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
	}

	/**
	 * Validate the regular statement descriptor.
	 *
	 * @param mixed           $value The value being validated.
	 * @param WP_REST_Request $request The request made.
	 * @param string          $param The parameter name, used in error messages.
	 * @return true|WP_Error
	 */
	public function validate_regular_statement_descriptor( $value, $request, $param ) {
		return $this->validate_statement_descriptor( $value, $request, $param, 22 );
	}

	/**
	 * Validate the short statement descriptor.
	 *
	 * @param mixed           $value The value being validated.
	 * @param WP_REST_Request $request The request made.
	 * @param string          $param The parameter name, used in error messages.
	 * @return true|WP_Error
	 */
	public function validate_short_statement_descriptor( $value, $request, $param ) {
		$is_short_account_statement_enabled = $request->get_param( 'is_short_statement_descriptor_enabled' );

		// bypassing validation to avoid errors in the client, it won't be updated under this condition
		if ( ! $is_short_account_statement_enabled ) {
			return true;
		}

		return $this->validate_statement_descriptor( $value, $request, $param, 10 );
	}

	/**
	 * Validate the statement descriptor argument.
	 *
	 * @since 4.7.0
	 *
	 * @param mixed           $value The value being validated.
	 * @param WP_REST_Request $request The request made.
	 * @param string          $param The parameter name, used in error messages.
	 * @param int             $max_length Maximum statement length.
	 * @return true|WP_Error
	 */
	public function validate_statement_descriptor( $value, $request, $param, $max_length ) {
		$string_validation_result = rest_validate_request_arg( $value, $request, $param );
		if ( true !== $string_validation_result ) {
			return $string_validation_result;
		}

		// Relaxing validation because it's blocking the user from saving it when they're on another tab of the settings screen
		// TODO: work that out with either a UX approach or handling the validations of each tab separately
		if ( '' === $value ) {
			return true;
		}

		try {
			$this->gateway->validate_account_statement_descriptor_field( $param, $value, $max_length );
		} catch ( Exception $exception ) {
			return new WP_Error(
				'rest_invalid_pattern',
				$exception->getMessage()
			);
		}

		return true;
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
				'is_stripe_enabled'                     => $this->gateway->is_enabled(),
				'is_test_mode_enabled'                  => $this->gateway->is_in_test_mode(),
				'title'                                 => $this->gateway->get_option( 'title' ),
				'title_upe'                             => $this->gateway->get_option( 'title_upe' ),
				'description'                           => $this->gateway->get_option( 'description' ),

				/* Settings > Payments accepted on checkout */
				'enabled_payment_method_ids'            => $this->gateway->get_upe_enabled_payment_method_ids(),
				'available_payment_method_ids'          => $this->gateway->get_upe_available_payment_methods(),

				/* Settings > Express checkouts */
				'is_payment_request_enabled'            => 'yes' === $this->gateway->get_option( 'payment_request' ),
				'payment_request_button_type'           => $this->gateway->get_option( 'payment_request_button_type' ),
				'payment_request_button_theme'          => $this->gateway->get_option( 'payment_request_button_theme' ),
				'payment_request_button_size'           => $this->gateway->get_option( 'payment_request_button_size' ),
				'payment_request_button_locations'      => $this->gateway->get_option( 'payment_request_button_locations' ),

				/* Settings > Payments & transactions */
				'is_manual_capture_enabled'             => ! $this->gateway->is_automatic_capture_enabled(),
				'is_saved_cards_enabled'                => 'yes' === $this->gateway->get_option( 'saved_cards' ),
				'is_separate_card_form_enabled'         => 'no' === $this->gateway->get_option( 'inline_cc_form' ),
				'statement_descriptor'                  => $this->gateway->get_option( 'statement_descriptor' ),
				'is_short_statement_descriptor_enabled' => 'yes' === $this->gateway->get_option( 'is_short_statement_descriptor_enabled' ),
				'short_statement_descriptor'            => $this->gateway->get_option( 'short_statement_descriptor' ),

				/* Settings > Advanced settings */
				'is_debug_log_enabled'                  => 'yes' === $this->gateway->get_option( 'logging' ),
				'is_upe_enabled'                        => WC_Stripe_Feature_Flags::is_upe_checkout_enabled(),
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
		$this->update_title( $request );
		$this->update_title_upe( $request );
		$this->update_description( $request );
		$this->update_is_test_mode_enabled( $request );

		/* Settings > Payments accepted on checkout */
		$this->update_enabled_payment_methods( $request );

		/* Settings > Express checkouts */
		$this->update_is_payment_request_enabled( $request );
		$this->update_payment_request_settings( $request );

		/* Settings > Payments & transactions */
		$this->update_is_manual_capture_enabled( $request );
		$this->update_is_saved_cards_enabled( $request );
		$this->update_is_separate_card_form_enabled( $request );
		$this->update_account_statement_descriptor( $request );
		$this->update_is_short_account_statement_enabled( $request );
		$this->update_short_account_statement_descriptor( $request );

		/* Settings > Advanced settings */
		$this->update_is_debug_log_enabled( $request );
		$this->update_is_upe_enabled( $request );

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
	 * Updates title.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_title( WP_REST_Request $request ) {
		$title = $request->get_param( 'title' );

		if ( null === $title ) {
			return;
		}

		$this->gateway->update_option( 'title', $title );
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

		$this->gateway->update_option( 'title_upe', $title_upe );
	}

	/**
	 * Updates description.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_description( WP_REST_Request $request ) {
		$description = $request->get_param( 'description' );

		if ( null === $description ) {
			return;
		}

		$this->gateway->update_option( 'description', $description );
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
	 * Updates account statement descriptor.
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
	 * Updates short account statement descriptor.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_short_account_statement_descriptor( WP_REST_Request $request ) {
		$is_short_account_statement_enabled = $request->get_param( 'is_short_statement_descriptor_enabled' );
		$short_account_statement_descriptor = $request->get_param( 'short_statement_descriptor' );

		// since we're bypassing the validation on the same condition, we shouldn't update it
		if ( ! $is_short_account_statement_enabled ) {
			return;
		}

		if ( null === $short_account_statement_descriptor ) {
			return;
		}

		$this->gateway->update_option( 'short_statement_descriptor', $short_account_statement_descriptor );
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
		if ( WC_Stripe_UPE_Compatibility::are_inbox_notes_supported() ) {
			require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-upe-availability-note.php';
			WC_Stripe_UPE_Availability_Note::possibly_delete_note();
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
			$this->gateway->update_option( $attribute, $value );
		}
	}

	/**
	 * Updates the list of enabled payment methods.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_enabled_payment_methods( WP_REST_Request $request ) {
		// no need to update the payment methods, if the UPE checkout is not enabled
		if ( ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			return;
		}

		$payment_method_ids_to_enable = $request->get_param( 'enabled_payment_method_ids' );

		if ( null === $payment_method_ids_to_enable ) {
			return;
		}

		$available_payment_methods = $this->gateway->get_upe_available_payment_methods();

		$payment_method_ids_to_enable = array_values(
			array_filter(
				$payment_method_ids_to_enable,
				function ( $payment_method ) use ( $available_payment_methods ) {
					return in_array( $payment_method, $available_payment_methods, true );
				}
			)
		);

		$this->gateway->update_option( 'upe_checkout_experience_accepted_payments', $payment_method_ids_to_enable );
	}
}
