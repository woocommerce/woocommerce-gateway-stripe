<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce Stripe Co-Branded Credit Card Payment Token.
 *
 * Representation of a payment token for co-branded credit cards.
 *
 * @class    WC_Payment_Token_CC_Co_Branded
 */
class WC_Payment_Token_CC_Co_Branded extends WC_Payment_Token_CC {
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
	 * Sets the preferred network (brand) for the card.
	 *
	 * @param string|null $preferred_network Preferred network (brand) for the card.
	 * @return void
	 */
	public function set_preferred_network( $preferred_network ) {
		$this->set_prop( 'preferred_network', $preferred_network );
	}
}
