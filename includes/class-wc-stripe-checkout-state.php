<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Checkout_State class.
 *
 * @since x.x.x
 */
final class WC_Stripe_Checkout_State {

	private const CHECKOUT_TYPE_CLASSIC   = 'classic';
	private const CHECKOUT_TYPE_STORE_API = 'store_api';

	private string $checkout_type = self::CHECKOUT_TYPE_CLASSIC;

	/**
	 * The singleton instance.
	 *
	 * @var WC_Stripe_Checkout_State|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return WC_Stripe_Checkout_State
	 */
	public static function get_instance(): WC_Stripe_Checkout_State {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Set the checkout type.
	 *
	 * @param string $checkout_type The checkout type.
	 * @return void
	 */
	private function set_checkout_type( string $checkout_type ): void {
		$this->checkout_type = $checkout_type;
	}

	/**
	 * Set the checkout type to store API.
	 *
	 * @return void
	 */
	public function set_store_api_checkout(): void {
		$this->set_checkout_type( self::CHECKOUT_TYPE_STORE_API );
	}

	/**
	 * Set the checkout type to classic.
	 *
	 * @return void
	 */
	public function set_classic_checkout(): void {
		$this->set_checkout_type( self::CHECKOUT_TYPE_CLASSIC );
	}

	/**
	 * Are we processing a store API checkout?
	 *
	 * @return bool
	 */
	public function is_store_api_checkout(): bool {
		return self::CHECKOUT_TYPE_STORE_API === $this->checkout_type;
	}

	/**
	 * Are we processing a classic checkout?
	 *
	 * @return bool
	 */
	public function is_classic_checkout(): bool {
		return self::CHECKOUT_TYPE_CLASSIC === $this->checkout_type;
	}
}
