<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce Stripe Credit Card Payment Token (with co-branded cards support).
 *
 * Representation of a payment token for co-branded credit cards.
 *
 * @class    WC_Payment_Token_CC_Stripe
 */
class WC_Payment_Token_CC_Stripe extends WC_Payment_Token_CC {

	/**
	 * Token Type String.
	 *
	 * @var string
	 */
	protected $type = 'CC_stripe';

	/**
	 * Stores Credit Card payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = [
		'last4'              => '',
		'expiry_year'        => '',
		'expiry_month'       => '',
		'card_type'          => '',
		'available_networks' => null,
		'preferred_network'  => null,
	];

	/**
	 * Returns true if the card is co-branded.
	 *
	 * @return bool
	 */
	public function is_co_branded() {
		return null !== $this->get_available_networks() && count( $this->get_available_networks() ) > 1;
	}

	/**
	 * Returns the list of available networks (brands) for the card.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return array|null List of available networks (brands) for the card.
	 */
	public function get_available_networks( $context = 'view' ) {
		return $this->get_prop( 'available_networks', $context );
	}

	/**
	 * Sets the list of available networks (brands) for the card.
	 *
	 * @param array $available_networks List of available networks (brands) for the card.
	 * @return void
	 */
	public function set_available_networks( $available_networks ) {
		$this->set_prop( 'available_networks', $available_networks );
	}

	/**
	 * Returns the preferred network (brand) for the card.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string|null Preferred network (brand) for the card.
	 */
	public function get_preferred_network( $context = 'view' ) {
		return $this->get_prop( 'preferred_network', $context );
	}

	/**
	 * Sets the preferred network (brand) for the card.
	 *
	 * @param string|null $preferred_network Preferred network (brand) for the card.
	 * @return void
	 */
	public function set_preferred_network( $preferred_network ) {
		$this->set_prop( 'preferred_network', $preferred_network );
	}
}
