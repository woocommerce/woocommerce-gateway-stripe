<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * General hook manager.
 *
 * @since 10.9.0
 */
class WC_Stripe_Hook_Manager {

	/**
	 * The singleton instance.
	 *
	 * @var WC_Stripe_Hook_Manager|null
	 */
	private static $instance = null;

	/**
	 * The registered plugin hooks.
	 *
	 * @var array<string, bool>
	 */
	private array $registered_plugin_hooks = [];

	/**
	 * The registered payment method hooks.
	 *
	 * @var array<string, array<string, bool>>
	 */
	private array $registered_payment_method_hooks = [];

	/**
	 * Get the singleton instance.
	 *
	 * @return WC_Stripe_Hook_Manager
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
	}

	/**
	 * Check if the plugin hooks are registered for a given hook category.
	 *
	 * @param string $hook_category The hook category to check.
	 * @return bool True if the plugin hooks are registered, false otherwise.
	 */
	public function are_plugin_hooks_registered( string $hook_category ): bool {
		if ( '' === $hook_category ) {
			return false;
		}

		return true === ( $this->registered_plugin_hooks[ $hook_category ] ?? false );
	}

	/**
	 * Register the plugin hooks for a given hook category.
	 *
	 * @param string $hook_category The hook category to register.
	 * @return bool True if the plugin hooks are registered, false otherwise.
	 */
	public function register_plugin_hooks( string $hook_category ): bool {
		if ( '' === $hook_category ) {
			return false;
		}

		if ( $this->are_plugin_hooks_registered( $hook_category ) ) {
			return false;
		}

		$this->registered_plugin_hooks[ $hook_category ] = true;
		return true;
	}

	/**
	 * Check if the payment method ID is valid.
	 *
	 * @param string $payment_method_id The payment method ID to check.
	 * @return bool True if the payment method ID is valid, false otherwise.
	 */
	public function is_valid_payment_method_id( string $payment_method_id ): bool {
		return 'stripe' === $payment_method_id || str_starts_with( $payment_method_id, 'stripe_' );
	}

	/**
	 * Check if the payment method hooks are registered for a given hook category.
	 *
	 * @param string $payment_method_id The payment method ID to check.
	 * @param string $hook_category     The hook category to check.
	 * @return bool True if the payment method hooks for the hook category are registered, false otherwise.
	 */
	public function are_payment_method_hooks_registered( string $payment_method_id, string $hook_category ): bool {
		if ( '' === $hook_category || ! $this->is_valid_payment_method_id( $payment_method_id ) ) {
			return false;
		}

		return true === ( $this->registered_payment_method_hooks[ $payment_method_id ][ $hook_category ] ?? false );
	}

	/**
	 * Register the payment method hooks for a given hook category.
	 *
	 * @param string $payment_method_id The payment method ID to register.
	 * @param string $hook_category     The hook category to register.
	 * @return bool True if the payment method hooks for the hook category are registered, false otherwise.
	 */
	public function register_payment_method_hooks( string $payment_method_id, string $hook_category ): bool {
		if ( '' === $hook_category || ! $this->is_valid_payment_method_id( $payment_method_id ) ) {
			return false;
		}

		if ( $this->are_payment_method_hooks_registered( $payment_method_id, $hook_category ) ) {
			return false;
		}

		if ( ! isset( $this->registered_payment_method_hooks[ $payment_method_id ] ) ) {
			$this->registered_payment_method_hooks[ $payment_method_id ] = [];
		}

		$this->registered_payment_method_hooks[ $payment_method_id ][ $hook_category ] = true;
		return true;
	}
}
