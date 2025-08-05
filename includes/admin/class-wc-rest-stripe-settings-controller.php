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
					'enabled_payment_method_ids'       => [
						'description'       => __( 'Payment method IDs that should be enabled. Other methods will be disabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => array_merge( $this->gateway->get_upe_available_payment_methods(), WC_Stripe_Helper::get_legacy_available_payment_method_ids() ),
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_oc_enabled'                    => [
						'description'       => __( 'If Optimized Checkout should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'amazon_pay_button_size'           => [
						'description'       => __( 'Express checkout button sizes.', 'woocommerce-gateway-stripe' ),
						'type'              => 'string',
						'enum'              => array_keys( $form_fields['amazon_pay_button_size']['options'] ?? [] ),
						'validate_callback' => 'rest_validate_request_arg',
					],
					'amazon_pay_button_locations'      => [
						'description'       => __( 'Express checkout locations that should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
							'enum' => array_keys( $form_fields['amazon_pay_button_locations']['options'] ?? [] ),
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
					'is_sepa_tokens_for_other_methods_enabled' => [
						'description'       => __( 'If "SEPA tokens for other methods" should be enabled.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_separate_card_form_enabled'    => [
						'description'       => __( 'If credit card number field, expiry date field, and CVC field should be separate.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_short_statement_descriptor_enabled' => [
						'description'       => __( 'When enabled, we\'ll include the order number for card and express checkout transactions.', 'woocommerce-gateway-stripe' ),
						'type'              => 'boolean',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'is_debug_log_enabled'             => [
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
							'enum' => array_merge( $this->gateway->get_upe_available_payment_methods(), WC_Stripe_Helper::get_legacy_available_payment_method_ids() ),
						],
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
				'callback'            => [ $this, 'dismiss_notice' ],
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
		$is_upe_enabled = WC_Stripe_Feature_Flags::is_upe_checkout_enabled();
		// When UPE and the payment method configurations API are enabled, fetch the enabled payment methods from the payment method configurations API.
		// We force a refresh of the enabled payment methods (by passing true) when on the settings page to ensure the latest data.
		// The available payment methods are also fetched from the payment method configurations API under similar conditions,
		// but we do not force a refresh for available methods, since calling get_upe_enabled_payment_method_ids first already ensures the list is up to date.
		$enabled_payment_method_ids   = $is_upe_enabled ? $this->gateway->get_upe_enabled_payment_method_ids( true ) : WC_Stripe_Helper::get_legacy_enabled_payment_method_ids();
		$available_payment_method_ids = $is_upe_enabled ? $this->gateway->get_upe_available_payment_methods() : WC_Stripe_Helper::get_legacy_available_payment_method_ids();
		$ordered_payment_method_ids   = $is_upe_enabled ? WC_Stripe_Helper::get_upe_ordered_payment_method_ids( $this->gateway ) : $available_payment_method_ids;

		return new WP_REST_Response(
			[
				/* Settings > General */
				'is_stripe_enabled'                        => $this->gateway->is_enabled(),
				'is_test_mode_enabled'                     => $this->gateway->is_in_test_mode(),

				/* Settings > Payments accepted on checkout */
				'enabled_payment_method_ids'               => array_values( array_intersect( $enabled_payment_method_ids, $available_payment_method_ids ) ), // only fetch enabled payment methods that are available.
				'available_payment_method_ids'             => $available_payment_method_ids,
				'ordered_payment_method_ids'               => array_values(
					array_diff(
						$ordered_payment_method_ids,
						[ WC_Stripe_Payment_Methods::AMAZON_PAY, WC_Stripe_Payment_Methods::LINK ]
					)
				), // exclude Amazon Pay and Link from this list as they are express methods only.
				/* Settings > Express checkouts */
				'amazon_pay_button_size'                   => $this->gateway->get_validated_option( 'amazon_pay_button_size' ),
				'amazon_pay_button_locations'              => $this->gateway->get_validated_option( 'amazon_pay_button_locations' ),
				'is_payment_request_enabled'               => $this->gateway->is_payment_request_enabled(),
				'payment_request_button_type'              => $this->gateway->get_validated_option( 'payment_request_button_type' ),
				'payment_request_button_theme'             => $this->gateway->get_validated_option( 'payment_request_button_theme' ),
				'payment_request_button_size'              => $this->gateway->get_validated_option( 'payment_request_button_size' ),
				'payment_request_button_locations'         => $this->gateway->get_validated_option( 'payment_request_button_locations' ),

				/* Settings > Payments & transactions */
				'is_manual_capture_enabled'                => ! $this->gateway->is_automatic_capture_enabled(),
				'is_saved_cards_enabled'                   => 'yes' === $this->gateway->get_option( 'saved_cards' ),
				'is_sepa_tokens_for_other_methods_enabled' => 'yes' === $this->gateway->get_option( 'sepa_tokens_for_other_methods' ),
				'is_separate_card_form_enabled'            => 'no' === $this->gateway->get_option( 'inline_cc_form' ),
				'is_short_statement_descriptor_enabled'    => 'yes' === $this->gateway->get_option( 'is_short_statement_descriptor_enabled' ),

				/* Settings > Advanced settings */
				'is_debug_log_enabled'                     => 'yes' === $this->gateway->get_option( 'logging' ),
				'is_upe_enabled'                           => $is_upe_enabled,
				'is_oc_enabled'                            => 'yes' === $this->gateway->get_option( 'optimized_checkout_element' ),
				'is_pmc_enabled'                           => 'yes' === $this->gateway->get_option( 'pmc_enabled' ),
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

		/* Settings > Payments accepted on checkout + Express checkouts */
		$payment_method_ids_to_enable = $this->get_payment_method_ids_to_enable( $request );
		$is_upe_enabled               = $request->get_param( 'is_upe_enabled' );
		$this->update_enabled_payment_methods( $payment_method_ids_to_enable, $is_upe_enabled );
		if ( ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() || ! WC_Stripe_Payment_Method_Configurations::is_enabled() ) {
			// We need to update a separate setting for legacy checkout.
			$this->update_is_payment_request_enabled_for_legacy_checkout( $request );
		}
		$this->update_payment_request_settings( $request );
		$this->update_amazon_pay_settings( $request );

		/* Settings > Payments & transactions */
		$this->update_is_manual_capture_enabled( $request );
		$this->update_is_saved_cards_enabled( $request );
		$this->update_is_sepa_tokens_for_other_methods_enabled( $request );
		$this->update_is_separate_card_form_enabled( $request );
		$this->update_is_short_account_statement_enabled( $request );

		/* Settings > Advanced settings */
		$this->update_is_debug_log_enabled( $request );
		$this->update_oc_settings( $request );

		return new WP_REST_Response( [], 200 );
	}

	/**
	 * Returns the payment method IDs to enable.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return string[]
	 */
	private function get_payment_method_ids_to_enable( WP_REST_Request $request ) {
		$payment_method_ids_to_enable = $request->get_param( 'enabled_payment_method_ids' );
		$is_upe_enabled               = $request->get_param( 'is_upe_enabled' );
		$is_payment_request_enabled   = $request->get_param( 'is_payment_request_enabled' );

		// Card is required for Apple Pay and Google Pay.
		if ( $is_upe_enabled &&
			 $is_payment_request_enabled &&
			 in_array( WC_Stripe_Payment_Methods::CARD, $payment_method_ids_to_enable, true )
		) {
			$payment_method_ids_to_enable = array_merge(
				$payment_method_ids_to_enable,
				[ WC_Stripe_Payment_Methods::APPLE_PAY, WC_Stripe_Payment_Methods::GOOGLE_PAY ]
			);
		}

		return $payment_method_ids_to_enable;
	}

	/**
	 * Updates the order of available payment methods in settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function update_payment_methods_order( WP_REST_Request $request ) {
		$is_upe_enabled             = WC_Stripe_Feature_Flags::is_upe_checkout_enabled();
		$ordered_payment_method_ids = $request->get_param( 'ordered_payment_method_ids' );

		if ( empty( $ordered_payment_method_ids ) ) {
			return new WP_REST_Response( [], 403 );
		}

		if ( $is_upe_enabled ) {
			$this->gateway->update_option( 'stripe_upe_payment_method_order', $ordered_payment_method_ids );
		} else {
			$ordered_payment_method_ids = array_map(
				function ( $id ) {
					if ( WC_Stripe_Payment_Methods::CARD === $id ) {
						return 'stripe';
					}
					return 'stripe_' . $id;
				},
				$ordered_payment_method_ids
			);

			$this->gateway->update_option( 'stripe_legacy_method_order', $ordered_payment_method_ids );
		}

		WC_Stripe_Helper::add_stripe_methods_in_woocommerce_gateway_order( $ordered_payment_method_ids );

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
	private function update_is_payment_request_enabled_for_legacy_checkout( WP_REST_Request $request ) {
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
	 * Updates "SEPA tokens for other methods" feature.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_is_sepa_tokens_for_other_methods_enabled( WP_REST_Request $request ) {
		$is_sepa_tokens_for_other_methods_enabled = $request->get_param( 'is_sepa_tokens_for_other_methods_enabled' );

		if ( null === $is_sepa_tokens_for_other_methods_enabled ) {
			return;
		}

		$this->gateway->update_option( 'sepa_tokens_for_other_methods', $is_sepa_tokens_for_other_methods_enabled ? 'yes' : 'no' );
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
	 * Updates appearance attributes of the Amazon Pay button.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_amazon_pay_settings( WP_REST_Request $request ) {
		$attributes = [
			'amazon_pay_button_size'      => 'amazon_pay_button_size',
			'amazon_pay_button_locations' => 'amazon_pay_button_locations',
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
	 * Updates the "Optimized Checkout" settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_oc_settings( WP_REST_Request $request ) {
		$attributes = [
			'is_oc_enabled' => 'optimized_checkout_element',
		];
		foreach ( $attributes as $request_key => $attribute ) {
			$value = $request->get_param( $request_key );

			if ( null === $value ) {
				continue;
			}

			$value         = 'is_oc_enabled' === $request_key ? ( $value ? 'yes' : 'no' ) : $value;
			$current_value = $this->gateway->get_option( $attribute );

			$this->gateway->update_validated_option( $attribute, $value );

			if ( 'is_oc_enabled' === $request_key && $value !== $current_value ) {
				wc_admin_record_tracks_event(
					$value ? 'wcstripe_oc_enabled' : 'wcstripe_oc_disabled',
					[
						'test_mode' => WC_Stripe_Mode::is_test() ? 1 : 0,
						'source'    => 'settings_rest_api',
					]
				);
			}
		}
	}

	/**
	 * Updates the list of enabled payment methods.
	 *
	 * @param array $payment_method_ids_to_enable The list of payment method ids to enable.
	 * @param bool $is_upe_enabled Whether UPE is enabled.
	 */
	private function update_enabled_payment_methods( $payment_method_ids_to_enable, $is_upe_enabled ) {
		if ( null === $is_upe_enabled ) {
			return;
		}

		if ( null === $payment_method_ids_to_enable ) {
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

		if ( $this->gateway instanceof WC_Stripe_UPE_Payment_Gateway ) {
			$this->gateway->update_enabled_payment_methods( $payment_method_ids_to_enable );
		}
	}

	/**
	 * Set `wc_stripe_show_customization_notice` as `no` to dismiss notice.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 *
	 * @deprecated since 9.6.0, use `dismiss_notice` instead.
	 */
	public function dismiss_customization_notice( WP_REST_Request $request ) {
		return $this->dismiss_notice( $request );
	}

	/**
	 * Dismisses settings notices such as the customization notice and BNPL promotion banner.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function dismiss_notice( WP_REST_Request $request ) {
		if ( null === $request->get_param( 'wc_stripe_show_customization_notice' )
			&& null === $request->get_param( 'wc_stripe_show_bnpl_promotion_banner' )
			&& null === $request->get_param( 'wc_stripe_show_oc_promotion_banner' ) ) {
			return new WP_REST_Response( [], 200 );
		}

		if ( null !== $request->get_param( 'wc_stripe_show_customization_notice' ) ) {
			update_option( 'wc_stripe_show_customization_notice', 'no' );
		}

		if ( null !== $request->get_param( 'wc_stripe_show_bnpl_promotion_banner' ) ) {
			update_option( 'wc_stripe_show_bnpl_promotion_banner', 'no' );
		}

		if ( null !== $request->get_param( 'wc_stripe_show_oc_promotion_banner' ) ) {
			update_option( 'wc_stripe_show_oc_promotion_banner', 'no' );
		}

		return new WP_REST_Response( [ 'result' => 'notice dismissed' ], 200 );
	}

	/**
	 * Record tracks events for each payment method that was enabled or disabled.
	 *
	 * @param array $enabled_methods An array of payment method ids that were enabled.
	 * @param array $disabled_methods An array of payment method ids that were disabled.
	 *
	 * @return void
	 */
	private function record_payment_method_settings_event( $enabled_methods, $disabled_methods ) {
		if ( ! function_exists( 'wc_admin_record_tracks_event' ) ) {
			return;
		}

		$is_test_mode = WC_Stripe_Mode::is_test();

		// Track the events for both arrays.
		array_map(
			function ( $id ) use ( $is_test_mode ) {
				wc_admin_record_tracks_event(
					'wcstripe_payment_method_settings_enabled',
					[
						'is_test_mode'   => $is_test_mode,
						'payment_method' => $id,
					]
				);
			},
			$enabled_methods
		);
		array_map(
			function ( $id ) use ( $is_test_mode ) {
				wc_admin_record_tracks_event(
					'wcstripe_payment_method_settings_disabled',
					[
						'is_test_mode'   => $is_test_mode,
						'payment_method' => $id,
					]
				);
			},
			$disabled_methods
		);
	}
}
