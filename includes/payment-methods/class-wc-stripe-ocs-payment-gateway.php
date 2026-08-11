<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gateway variant used when Optimized Checkout (OCS) is the active payment strategy.
 *
 * All OCS-aware behavior lives here; the base {@see WC_Stripe_UPE_Payment_Gateway} handles the
 * classic UPE flow. {@see WC_Stripe::get_main_stripe_gateway()} picks the instance to use.
 */
class WC_Stripe_OCS_Payment_Gateway extends WC_Stripe_UPE_Payment_Gateway {

	/**
	 * Constructor.
	 *
	 * Marks this gateway instance as the Optimized Checkout variant.
	 */
	public function __construct() {
		parent::__construct();
		$this->oc_enabled = true;
	}

	/**
	 * Whether OCS is the active payment strategy for the current request.
	 *
	 * Broader than the render gate: true wherever OCS-aware behavior such as token merging
	 * applies (for example, My Account → Payment Methods).
	 *
	 * @return bool
	 */
	public function is_optimized_checkout_active(): bool {
		return ! $this->is_on_add_payment_method_page()
			&& ! $this->is_changing_payment_method_for_subscription();
	}

	/**
	 * Whether the current page is one where the OCS Payment Element may render.
	 *
	 * @return bool
	 */
	public function is_valid_optimized_checkout_page(): bool {
		if ( $this->is_on_add_payment_method_page() || $this->is_changing_payment_method_for_subscription() ) {
			return false;
		}

		return is_checkout();
	}

	/**
	 * Whether the single OC element should represent Stripe for the current request.
	 *
	 * Broader than {@see self::is_valid_optimized_checkout_page()}: it also covers the
	 * Cart/Checkout block editor, where is_checkout() is false but the OC element must still
	 * register as 'stripe' so the block reports Stripe as a supported payment method.
	 *
	 * @return bool
	 */
	public function should_render_optimized_checkout(): bool {
		return $this->is_valid_optimized_checkout_page()
			|| ( is_admin() && WC_Stripe::get_instance()->is_editing_cart_or_checkout_block() );
	}

	/**
	 * Returns the payment method title.
	 *
	 * Uses the OC title on checkout pages, and the order's stored payment-method title on the
	 * order-received / order-details pages.
	 *
	 * @return string
	 */
	public function get_title(): string {
		// The order-received (thank you) and order-details (admin / My Account view-order) pages
		// aren't checkout pages, so they fall outside is_valid_optimized_checkout_page(). Prefer the
		// payment-method title stored on the order; resolve it before the render gate using the
		// broad active check so it still applies on the admin order screen.
		if ( $this->is_optimized_checkout_active() && ( is_wc_endpoint_url( 'order-received' ) || $this->is_order_details_page() ) ) {
			global $theorder;
			if ( $theorder instanceof WC_Order ) {
				$checkout_session_id = WC_Stripe_Order_Helper::get_instance()->get_stripe_checkout_session_id( $theorder );
				if ( ! empty( $checkout_session_id ) ) {
					$title = $theorder->get_payment_method_title();
					if ( ! empty( $title ) ) {
						return $title;
					}
				}
			}
		}

		if ( ! $this->is_valid_optimized_checkout_page() ) {
			return parent::get_title();
		}

		// Use the static title helper rather than instantiating WC_Stripe_UPE_Payment_Method_OC:
		// that class mimics the 'stripe' gateway id and re-registers subscription hooks in its
		// constructor, which previously caused duplicate renewal charges (see #5325).
		return WC_Stripe_UPE_Payment_Method_OC::get_alternative_title();
	}

	/**
	 * Adds OCS-specific keys to the params passed to the client scripts.
	 *
	 * @return array
	 */
	public function javascript_params(): array {
		$stripe_params = parent::javascript_params();

		$should_show_optimized_checkout                 = $this->should_render_optimized_checkout();
		$stripe_params['isOCEnabled']                   = $should_show_optimized_checkout;
		$stripe_params['shouldShowOptimizedCheckout']   = $should_show_optimized_checkout;
		$stripe_params['shouldExpandOptimizedCheckout'] = $should_show_optimized_checkout && WC_Stripe_Feature_Flags::should_expand_ocs_in_legacy_checkout();
		$stripe_params['isAdaptivePricingEnabled']      = $should_show_optimized_checkout && $this->is_adaptive_pricing_supported();

		if ( $should_show_optimized_checkout ) {
			$stripe_params['OCLayout']                     = $this->get_option( 'optimized_checkout_layout', self::OPTIMIZED_CHECKOUT_DEFAULT_LAYOUT );
			$stripe_params['paymentMethodConfigurationId'] = WC_Stripe_Payment_Method_Configurations::get_configuration_id();
			$stripe_params['excludedPaymentMethodTypes']   = $this->get_excluded_payment_method_types();
			// The country-derived portion on its own, so the client recompute can
			// subtract exactly it from the seed and preserve third-party additions.
			$stripe_params['countryExcludedPaymentMethodTypes'] = $this->get_country_excluded_payment_method_types();
			$stripe_params['optimizedCheckoutClassicTitle']     = WC_Stripe_UPE_Payment_Method_OC::get_classic_title();
		}

		return $stripe_params;
	}

	/**
	 * Builds the enabled-payment-method config passed to the client.
	 *
	 * Surfaces the consolidated `oc` method alongside express methods; the individual UPE
	 * sub-methods render inside the Payment Element's card container.
	 *
	 * @return array
	 */
	protected function get_enabled_payment_method_config(): array {
		$settings = [];

		$enabled_payment_methods = $this->get_upe_enabled_at_checkout_payment_method_ids();
		$original_method_ids     = $enabled_payment_methods;
		$payment_methods         = $this->payment_methods;

		if ( $this->should_render_optimized_checkout() ) {
			$oc_method_id                     = WC_Stripe_UPE_Payment_Method_OC::STRIPE_ID;
			$enabled_express_methods          = array_intersect(
				$enabled_payment_methods,
				WC_Stripe_Payment_Methods::EXPRESS_PAYMENT_METHODS
			);
			$enabled_payment_methods          = array_merge( [ $oc_method_id ], $enabled_express_methods );
			$payment_methods[ $oc_method_id ] = new WC_Stripe_UPE_Payment_Method_OC();
		}

		// Compute per-method showSaveOption so the frontend can dynamically toggle the save
		// checkbox as the customer switches methods inside the Payment Element. With Adaptive
		// Pricing, methods saved as a different type (Bancontact → SEPA) are not savable: the
		// Checkout Sessions flow cannot request `setup_future_usage` for them.
		$show_save_option_by_method = [];

		// OC hides each method's `countries` config from the frontend; expose a map so it
		// can recompute `excludedPaymentMethodTypes` on billing-country changes.
		$countries_by_method = [];
		if ( $this->should_render_optimized_checkout() ) {
			$is_adaptive_pricing_active = $this->is_adaptive_pricing_supported();
			$non_excludable_methods     = $this->get_non_excludable_payment_method_types();
			foreach ( $original_method_ids as $method_id ) {
				if ( isset( $this->payment_methods[ $method_id ] ) ) {
					$payment_method                 = $this->payment_methods[ $method_id ];
					$is_saved_as_different_type     = $payment_method->get_id() !== $payment_method->get_retrievable_type();
					$is_blocked_by_adaptive_pricing = $is_adaptive_pricing_active && $is_saved_as_different_type;

					$show_save_option_by_method[ $method_id ] = ! $is_blocked_by_adaptive_pricing
						&& $this->should_upe_payment_method_show_save_option( $payment_method );

					// Leave non-excludable methods out of the map so the client
					// recompute can never re-exclude what the server refuses to.
					$method_countries = $payment_method->get_available_billing_countries();
					if ( ! empty( $method_countries ) && ! in_array( $method_id, $non_excludable_methods, true ) ) {
						$countries_by_method[ $method_id ] = $method_countries;
					}
				}
			}

			// Adaptive Pricing can present methods the store-currency list above
			// excludes; without an entry, the client's fallback shows the checkbox.
			if ( $is_adaptive_pricing_active ) {
				foreach ( $this->get_upe_enabled_payment_method_ids() as $method_id ) {
					if ( ! isset( $this->payment_methods[ $method_id ] )
						|| array_key_exists( $method_id, $show_save_option_by_method ) ) {
						continue;
					}

					$payment_method = $this->payment_methods[ $method_id ];
					if ( $payment_method->get_id() !== $payment_method->get_retrievable_type() ) {
						$show_save_option_by_method[ $method_id ] = false;
					}
				}
			}
		}

		foreach ( $enabled_payment_methods as $payment_method_id ) {
			$payment_method = $payment_methods[ $payment_method_id ];

			$settings[ $payment_method_id ] = [
				'isReusable'             => $payment_method->is_reusable(),
				'title'                  => $payment_method->get_title(),
				'description'            => $payment_method->get_description(),
				'testingInstructions'    => self::expand_copy_button_markup( $payment_method->get_testing_instructions( false, false ) ),
				'showSaveOption'         => $this->should_upe_payment_method_show_save_option( $payment_method ),
				'supportsDeferredIntent' => $payment_method->supports_deferred_intent(),
				'countries'              => $payment_method->get_available_billing_countries(),
				'enabledPaymentMethods'  => $original_method_ids,
			];

			if ( $payment_method instanceof WC_Stripe_UPE_Payment_Method_OC ) {
				if ( ! empty( $show_save_option_by_method ) ) {
					$settings[ $payment_method_id ]['showSaveOptionByMethod'] = $show_save_option_by_method;
				}

				if ( ! empty( $countries_by_method ) ) {
					$settings[ $payment_method_id ]['countriesByMethod'] = $countries_by_method;
				}
			}
		}

		return $settings;
	}

	/**
	 * Renders the test-mode testing-instructions block inside payment_fields().
	 *
	 * The OC instructions are per-method `<div>` blocks toggled client-side as the customer
	 * switches methods inside the Payment Element, so they can't live inside the base `<p>` wrapper.
	 *
	 * @return void
	 */
	protected function render_testing_instructions(): void {
		if ( ! $this->is_valid_optimized_checkout_page() ) {
			parent::render_testing_instructions();
			return;
		}

		echo wp_kses(
			self::expand_copy_button_markup( $this->get_testing_instructions_payment_method()->get_testing_instructions() ),
			$this->get_testing_instructions_allowed_tags()
		);
	}

	/**
	 * The payment method whose testing instructions render in test mode.
	 *
	 * @return WC_Stripe_UPE_Payment_Method
	 */
	protected function get_testing_instructions_payment_method(): WC_Stripe_UPE_Payment_Method {
		if ( $this->is_valid_optimized_checkout_page() ) {
			return new WC_Stripe_UPE_Payment_Method_OC();
		}
		return parent::get_testing_instructions_payment_method();
	}

	/**
	 * Allowed HTML tags for testing-instruction output.
	 *
	 * Adds the `div` wrappers used by the per-method OC instruction blocks.
	 *
	 * @return array
	 */
	protected function get_testing_instructions_allowed_tags(): array {
		$allowed_tags = parent::get_testing_instructions_allowed_tags();

		if ( $this->is_valid_optimized_checkout_page() ) {
			$allowed_tags['div'] = [
				'id'    => [],
				'class' => [],
				'style' => [],
			];
		}

		return $allowed_tags;
	}

	/**
	 * Adds the adaptive-pricing currency selector above the Payment Element when applicable.
	 *
	 * @param bool $display_tokenization Whether saved-method tokenization is being displayed.
	 *
	 * @return void
	 */
	protected function render_payment_fields_above_element( bool $display_tokenization ): void {
		parent::render_payment_fields_above_element( $display_tokenization );

		if ( $this->is_valid_optimized_checkout_page() && $this->is_adaptive_pricing_supported() ) {
			echo '<div id="wc-stripe-currency-selector" class="wc-stripe-currency-selector" style="margin-top: 12px;"></div>';
		}
	}

	/**
	 * Whether the store-level save-method checkbox should be hidden.
	 *
	 * Under OCS the Payment Element handles Link save consent per method, so the classic
	 * hide-for-Link rule does not apply.
	 *
	 * @return bool
	 */
	protected function should_hide_save_payment_method_checkbox(): bool {
		if ( $this->is_valid_optimized_checkout_page() ) {
			return false;
		}
		return parent::should_hide_save_payment_method_checkbox();
	}

	/**
	 * Records the payment method title on the order.
	 *
	 * Ensures the OC payment method instance is present before recording an OC-typed title.
	 *
	 * @param WC_Order      $order                 WC order being processed.
	 * @param string        $payment_method_type   Stripe payment method key.
	 * @param stdClass|bool $stripe_payment_method Stripe payment method object.
	 *
	 * @return void
	 */
	public function set_payment_method_title_for_order( $order, $payment_method_type, $stripe_payment_method = false ): void {
		if ( WC_Stripe_Payment_Methods::OC === $payment_method_type && ! isset( $this->payment_methods[ WC_Stripe_Payment_Methods::OC ] ) ) {
			$this->payment_methods[ WC_Stripe_Payment_Methods::OC ] = new WC_Stripe_UPE_Payment_Method_OC();
		}
		parent::set_payment_method_title_for_order( $order, $payment_method_type, $stripe_payment_method );
	}

	/**
	 * Merges saved sub-gateway tokens into the consolidated `stripe` gateway when OCS is active.
	 *
	 * Sub-gateways (SEPA, ACH, Amazon Pay, etc.) are surfaced so customers see every reusable
	 * method in a single list.
	 *
	 * @return WC_Payment_Token[]
	 */
	public function get_tokens(): array {
		$tokens = parent::get_tokens();

		// Use the broad active check (not the page-specific render gate): saved sub-gateway
		// tokens must also surface on My Account → Payment Methods, which is not a checkout page.
		if ( ! is_user_logged_in() || ! $this->is_optimized_checkout_active() ) {
			return $tokens;
		}

		$fetched_gateway_ids = [];
		foreach ( $tokens as $token ) {
			$fetched_gateway_ids[ $token->get_gateway_id() ] = true;
		}

		foreach ( $this->get_upe_enabled_payment_method_ids() as $stripe_id ) {
			if ( ! array_key_exists( $stripe_id, WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD ) ) {
				continue;
			}

			if ( ! $this->is_enabled_at_checkout( $stripe_id ) ) {
				continue;
			}

			$gateway_id = WC_Stripe_Payment_Tokens::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ $stripe_id ];

			if ( isset( $fetched_gateway_ids[ $gateway_id ] ) ) {
				continue;
			}

			$fetched_gateway_ids[ $gateway_id ] = true;
			$method_tokens                      = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), $gateway_id );
			$tokens                             = array_merge( $tokens, $method_tokens );
		}

		// Deduplicate by WooCommerce token ID (array_unique is unreliable for WC_Payment_Token objects).
		$seen   = [];
		$unique = [];
		foreach ( $tokens as $token ) {
			$token_id = $token->get_id();
			if ( ! isset( $seen[ $token_id ] ) ) {
				$seen[ $token_id ]   = true;
				$unique[ $token_id ] = $token;
			}
		}
		return $unique;
	}

	/**
	 * Resolves the UPE payment method instance used for a payment being processed.
	 *
	 * Under OCS the method is derived from the Stripe-returned payment_method_details type
	 * rather than the form-sent selected_payment_type.
	 *
	 * @param array $payment_information Payment information array used in process_payment.
	 *
	 * @return WC_Stripe_UPE_Payment_Method|null
	 */
	protected function resolve_upe_payment_method_for_processing( array $payment_information ): ?WC_Stripe_UPE_Payment_Method {
		$details = $payment_information['payment_method_details'] ?? null;
		if ( isset( $details->type ) ) {
			$instance = self::get_payment_method_instance( $details->type );
			if ( $instance ) {
				return $instance;
			}
		}
		return parent::resolve_upe_payment_method_for_processing( $payment_information );
	}

	/**
	 * Resolves the payment-method instance for a saved token created from a setup intent.
	 *
	 * Under OCS the setup intent's payment_method_details->type is authoritative.
	 *
	 * @param string             $payment_method_type    Stripe payment method type key.
	 * @param array|object|false $payment_method_details Payment method details array or object (false when unavailable).
	 *
	 * @return WC_Stripe_UPE_Payment_Method|null
	 */
	protected function resolve_payment_method_for_setup_intent( $payment_method_type, $payment_method_details ): ?WC_Stripe_UPE_Payment_Method {
		$resolved_type = null;
		if ( is_array( $payment_method_details ) ) {
			$resolved_type = $payment_method_details['type'] ?? null;
		} elseif ( is_object( $payment_method_details ) ) {
			$resolved_type = $payment_method_details->type ?? null;
		}

		if ( ! empty( $resolved_type ) ) {
			$instance = self::get_payment_method_instance( $resolved_type );
			if ( $instance ) {
				return $instance;
			}
		}
		return parent::resolve_payment_method_for_setup_intent( $payment_method_type, $payment_method_details );
	}

	/**
	 * Resolves the selected payment type + payment method types sent to Stripe when creating an intent.
	 *
	 * Drives intent creation from payment_method_details->type, with a narrow fallback to the
	 * express payment type, and re-checks reusability against the resolved type.
	 *
	 * @param string      $selected_payment_type        Currently selected payment type.
	 * @param string|null $payment_method_id            Stripe payment method id (may be empty for express flows).
	 * @param object      $payment_method_details       Payment method details object from Stripe.
	 * @param WC_Order    $order                        WC order being paid.
	 * @param string|null $express_payment_type         Express checkout type if applicable.
	 * @param bool        $save_payment_method_to_store Whether the payment method should be saved.
	 *
	 * @return array{ selected_payment_type: string, payment_method_types: string[], save_payment_method_to_store: bool }
	 */
	protected function resolve_intent_payment_method_types( string $selected_payment_type, ?string $payment_method_id, object $payment_method_details, WC_Order $order, ?string $express_payment_type, bool $save_payment_method_to_store = false ): array {
		if ( empty( $payment_method_id ) || empty( $payment_method_details->type ) ) {
			// Express paths won't have a payment method created yet; fall back to the express type.
			if ( '' === $selected_payment_type && null !== $express_payment_type ) {
				$selected_payment_type = $express_payment_type;
			}
		} else {
			$selected_payment_type = $payment_method_details->type;
		}

		// Re-check reusability against the resolved type; the earlier save signal was computed against the OC pseudo-method.
		if (
			$save_payment_method_to_store &&
			isset( $this->payment_methods[ $selected_payment_type ] ) &&
			! $this->payment_methods[ $selected_payment_type ]->is_reusable()
		) {
			$save_payment_method_to_store = false;
		}

		return [
			'selected_payment_type'        => $selected_payment_type,
			'payment_method_types'         => [ $selected_payment_type ],
			'save_payment_method_to_store' => $save_payment_method_to_store,
		];
	}

	/**
	 * Resolves the payment method type used to build payment_method_options on the intent.
	 *
	 * Prefers the Stripe-returned payment_method_details->type over the form-sent type.
	 *
	 * @param string $selected_payment_type  Selected payment type from checkout.
	 * @param object $payment_method_details Payment method details from Stripe.
	 *
	 * @return string
	 */
	protected function resolve_payment_method_type_for_options( string $selected_payment_type, $payment_method_details ): string {
		if ( isset( $payment_method_details->type ) ) {
			return (string) $payment_method_details->type;
		}
		return parent::resolve_payment_method_type_for_options( $selected_payment_type, $payment_method_details );
	}

	/**
	 * Resolves the payment method type + instance used when saving a token from a successful payment.
	 *
	 * Reflects the concrete type Stripe returns rather than the consolidated OC handle.
	 *
	 * @param string $payment_method_type   Payment method type currently in scope.
	 * @param object $payment_method_object Payment method object from Stripe.
	 *
	 * @return array{0: string, 1: WC_Stripe_UPE_Payment_Method|null}
	 */
	protected function resolve_payment_method_for_saved_token( string $payment_method_type, $payment_method_object ): array {
		if ( isset( $payment_method_object->type ) ) {
			$payment_method_type = $payment_method_object->type;
			$instance            = $this->get_payment_method_instance( $payment_method_type );
			if ( $instance ) {
				return [ $payment_method_type, $instance ];
			}
		}
		return parent::resolve_payment_method_for_saved_token( $payment_method_type, $payment_method_object );
	}

	/**
	 * Resolves the selected payment type represented by a payment information array.
	 *
	 * The real type lives in payment_method_details->type; the info's selected_payment_type is
	 * always the consolidated OC handle under OCS.
	 *
	 * @param array $payment_information Payment information array.
	 *
	 * @return string
	 */
	public function get_selected_payment_type_from_info( array $payment_information ): string {
		$details = $payment_information['payment_method_details'] ?? null;
		if ( isset( $details->type ) ) {
			return (string) $details->type;
		}
		return parent::get_selected_payment_type_from_info( $payment_information );
	}
}
