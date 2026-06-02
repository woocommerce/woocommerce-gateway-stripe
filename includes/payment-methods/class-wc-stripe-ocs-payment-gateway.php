<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gateway variant used when Optimized Checkout (OCS) is the active payment strategy.
 *
 * All OCS-aware behavior (consolidated Payment Element rendering, payment-method-details
 * routing, saved-token merging across sub-gateways, OC-specific title/params) lives here.
 * The base {@see WC_Stripe_UPE_Payment_Gateway} is unaware of OCS and handles the classic
 * UPE flow. {@see WC_Stripe::get_main_stripe_gateway()} picks the right instance based on
 * the `optimized_checkout_element` setting.
 */
class WC_Stripe_OCS_Payment_Gateway extends WC_Stripe_UPE_Payment_Gateway {

	/**
	 * Layout used when a store hasn't explicitly configured the OCS layout option.
	 */
	public const DEFAULT_LAYOUT = 'accordion';

	public function __construct() {
		parent::__construct();
		$this->oc_enabled = true;
	}

	/**
	 * Whether Optimized Checkout is the active payment strategy for the current request.
	 *
	 * Distinct from {@see self::is_valid_optimized_checkout_page()} — which gates *rendering*
	 * to actual checkout pages. This broader check is true wherever OCS-aware behavior
	 * should apply (for example, My Account → Payment Methods, where saved tokens for
	 * sub-gateways must still surface under the consolidated `stripe` gateway).
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
	 * Returns the payment method title.
	 *
	 * On OCS checkout pages, use the OC payment method's title. On order received/details
	 * pages, the OC title falls through to generic "Stripe"; prefer the title stored on the
	 * order by the webhook handler when available.
	 */
	public function get_title() {
		if ( ! $this->is_valid_optimized_checkout_page() ) {
			return parent::get_title();
		}

		if ( is_wc_endpoint_url( 'order-received' ) || $this->is_order_details_page() ) {
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

		return ( new WC_Stripe_UPE_Payment_Method_OC() )->get_title();
	}

	/**
	 * Adds OCS-specific keys to the params passed to the client scripts.
	 */
	public function javascript_params() {
		$stripe_params = parent::javascript_params();

		$should_show_optimized_checkout                 = $this->is_valid_optimized_checkout_page();
		$stripe_params['isOCEnabled']                   = $should_show_optimized_checkout;
		$stripe_params['shouldShowOptimizedCheckout']   = $should_show_optimized_checkout;
		$stripe_params['shouldExpandOptimizedCheckout'] = $should_show_optimized_checkout && WC_Stripe_Feature_Flags::should_expand_ocs_in_legacy_checkout();
		$stripe_params['isAdaptivePricingEnabled']      = $should_show_optimized_checkout && $this->is_adaptive_pricing_supported();

		if ( $should_show_optimized_checkout ) {
			$stripe_params['OCLayout']                      = $this->get_option( 'optimized_checkout_layout', self::DEFAULT_LAYOUT );
			$stripe_params['paymentMethodConfigurationId']  = WC_Stripe_Payment_Method_Configurations::get_configuration_id();
			$stripe_params['excludedPaymentMethodTypes']    = $this->get_excluded_payment_method_types();
			$stripe_params['optimizedCheckoutClassicTitle'] = WC_Stripe_UPE_Payment_Method_OC::get_classic_title();
		}

		return $stripe_params;
	}

	/**
	 * Under OCS, the consolidated `oc` payment method is rendered alongside express methods;
	 * the individual UPE sub-methods are surfaced inside the Payment Element's card container.
	 */
	protected function get_enabled_payment_method_config() {
		$settings = [];

		$enabled_payment_methods = $this->get_upe_enabled_at_checkout_payment_method_ids();
		$original_method_ids     = $enabled_payment_methods;
		$payment_methods         = $this->payment_methods;

		if ( $this->is_valid_optimized_checkout_page() ) {
			$oc_method_id                     = WC_Stripe_UPE_Payment_Method_OC::STRIPE_ID;
			$enabled_express_methods          = array_intersect(
				$enabled_payment_methods,
				WC_Stripe_Payment_Methods::EXPRESS_PAYMENT_METHODS
			);
			$enabled_payment_methods          = array_merge( [ $oc_method_id ], $enabled_express_methods );
			$payment_methods[ $oc_method_id ] = new WC_Stripe_UPE_Payment_Method_OC();
		}

		// Compute per-method showSaveOption so the frontend can dynamically toggle the save
		// checkbox as the customer switches methods inside the Payment Element.
		$show_save_option_by_method = [];
		if ( $this->is_valid_optimized_checkout_page() ) {
			foreach ( $original_method_ids as $method_id ) {
				if ( isset( $this->payment_methods[ $method_id ] ) ) {
					$show_save_option_by_method[ $method_id ] = $this->should_upe_payment_method_show_save_option( $this->payment_methods[ $method_id ] );
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

			if ( ! empty( $show_save_option_by_method ) && $payment_method instanceof WC_Stripe_UPE_Payment_Method_OC ) {
				$settings[ $payment_method_id ]['showSaveOptionByMethod'] = $show_save_option_by_method;
			}
		}

		return $settings;
	}

	/**
	 * Overrides the base testing-instructions rendering to output the consolidated OC instructions
	 * when OCS is rendered.
	 */
	/**
	 * Renders the payment form on checkout pages.
	 *
	 * @return void
	 */
	public function payment_fields() {
		if ( ! $this->is_valid_optimized_checkout_page() ) {
			parent::payment_fields();
			return;
		}

		try {
			$display_tokenization = $this->supports( 'tokenization' ) && is_checkout() && $this->saved_cards;

			?>
			<?php if ( ! empty( $this->get_description() ) ) : ?>
				<p><?php echo wp_kses_post( $this->get_description() ); ?></p>
			<?php endif; ?>

			<?php
			if ( $this->testmode ) :
				echo wp_kses(
					self::expand_copy_button_markup( ( new WC_Stripe_UPE_Payment_Method_OC() )->get_testing_instructions() ),
					array_merge(
						[
							'div' => [
								'id'    => [],
								'class' => [],
								'style' => [],
							],
						],
						$this->get_testing_instructions_allowed_tags()
					)
				);
			endif;
			?>

			<?php $this->render_payment_fields_above_element( $display_tokenization ); ?>

			<fieldset id="wc-stripe-upe-form" class="wc-upe-form wc-payment-form">
				<div class="wc-stripe-upe-element" data-payment-method-type="<?php echo esc_attr( WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID ); ?>"></div>
				<div id="wc-stripe-upe-errors" role="alert"></div>
				<input id="wc-stripe-payment-method-upe" type="hidden" name="wc-stripe-payment-method-upe" />
				<input id="wc_stripe_selected_upe_payment_type" type="hidden" name="wc_stripe_selected_upe_payment_type" />
				<?php // Hidden input for appearance style extraction on non-checkout pages (Add Payment Method, Order Pay). ?>
				<input type="text" id="wc-stripe-hidden-style-input" class="input-text" aria-hidden="true" tabindex="-1" autocomplete="off" style="position:absolute!important;opacity:0!important;pointer-events:none!important;" />
			</fieldset>
			<?php
			$methods_enabled_for_saved_payments = array_filter( $this->get_upe_enabled_payment_method_ids(), [ $this, 'is_enabled_for_saved_payments' ] );
			if ( $this->is_saved_cards_enabled() && ! empty( $methods_enabled_for_saved_payments ) ) {
				$force_save_payment = ( $display_tokenization && ! apply_filters( 'wc_stripe_display_save_payment_method_checkbox', $display_tokenization ) ) || is_add_payment_method_page() || WC_Stripe_Helper::should_force_save_payment_method();
				$this->save_payment_method_checkbox( $force_save_payment );
			}

			do_action( 'wc_stripe_payment_fields_' . $this->id, $this->id );
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Error in OCS payment fields.', [ 'error_message' => $e->getMessage() ] );
			?>
			<div>
				<?php echo esc_html__( 'An error was encountered when preparing the payment form. Please try again later.', 'woocommerce-gateway-stripe' ); ?>
			</div>
			<?php
		}
	}

	/**
	 * Adds the adaptive-pricing currency selector above the Payment Element when applicable.
	 */
	protected function render_payment_fields_above_element( bool $display_tokenization ): void {
		parent::render_payment_fields_above_element( $display_tokenization );

		if ( $this->is_valid_optimized_checkout_page() && $this->is_adaptive_pricing_supported() ) {
			echo '<div id="wc-stripe-currency-selector" class="wc-stripe-currency-selector" style="margin-top: 12px;"></div>';
		}
	}

	/**
	 * Under OCS the Payment Element already handles Link save consent at a per-method level,
	 * so the classic hide-for-Link rule does not apply.
	 */
	protected function should_hide_save_payment_method_checkbox(): bool {
		if ( $this->is_valid_optimized_checkout_page() ) {
			return false;
		}
		return parent::should_hide_save_payment_method_checkbox();
	}

	/**
	 * Ensures the OC payment method instance is present when recording an OC-typed order title.
	 */
	/**
	 * Records the payment method title on the order.
	 *
	 * @return void
	 */
	public function set_payment_method_title_for_order( $order, $payment_method_type, $stripe_payment_method = false ) {
		if ( WC_Stripe_Payment_Methods::OC === $payment_method_type && ! isset( $this->payment_methods[ WC_Stripe_Payment_Methods::OC ] ) ) {
			$this->payment_methods[ WC_Stripe_Payment_Methods::OC ] = new WC_Stripe_UPE_Payment_Method_OC();
		}
		parent::set_payment_method_title_for_order( $order, $payment_method_type, $stripe_payment_method );
	}

	/**
	 * Adds saved tokens from sub-gateways (SEPA, ACH, Amazon Pay, etc.) to the consolidated
	 * `stripe` gateway when OCS is active, so customers see every reusable method in one list.
	 */
	public function get_tokens() {
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
	 * Under OCS, resolve the payment method from the Stripe-returned payment_method_details
	 * rather than the selected_payment_type that the classic UPE form sends.
	 */
	protected function resolve_upe_payment_method_for_processing( array $payment_information ) {
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
	 * Under OCS, the setup intent's payment_method_details->type is authoritative.
	 */
	protected function resolve_payment_method_for_setup_intent( $payment_method_type, $payment_method_details ) {
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
	 * Under OCS, use the payment_method_details->type (set by the Payment Element) to drive
	 * intent creation, with a narrow fallback to the express payment type.
	 */
	protected function resolve_intent_payment_method_types( $selected_payment_type, $payment_method_id, $payment_method_details, $order, $express_payment_type, $save_payment_method_to_store = false ) {
		if ( empty( $payment_method_id ) || empty( $payment_method_details->type ) ) {
			// Express paths won't have a payment method created yet; fall back to either the
			// currently selected type or the express type (whichever is set).
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
	 * Under OCS, prefer the Stripe-returned payment_method_details->type over the form-sent
	 * selected_payment_type when building payment_method_options.
	 */
	protected function resolve_payment_method_type_for_options( $selected_payment_type, $payment_method_details ) {
		if ( isset( $payment_method_details->type ) ) {
			return $payment_method_details->type;
		}
		return parent::resolve_payment_method_type_for_options( $selected_payment_type, $payment_method_details );
	}

	/**
	 * Under OCS, saved tokens should reflect the concrete payment-method type Stripe returns
	 * (rather than the form-sent type, which is always the consolidated OC handle).
	 */
	protected function resolve_payment_method_for_saved_token( $payment_method_type, $payment_method_object ) {
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
	 * Under OCS the selected_payment_type on the payment information is always the consolidated
	 * OC handle; the real type sits in payment_method_details->type.
	 */
	public function get_selected_payment_type_from_info( array $payment_information ) {
		$details = $payment_information['payment_method_details'] ?? null;
		if ( isset( $details->type ) ) {
			return $details->type;
		}
		return parent::get_selected_payment_type_from_info( $payment_information );
	}
}
