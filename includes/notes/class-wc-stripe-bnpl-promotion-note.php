<?php
/**
 * Display a notice to merchants to promote BNPL (Buy Now Pay Later) payment methods.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\NoteTraits;
use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\WC_Admin_Note;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Stripe_BNPL_Promotion_Note
 */
class WC_Stripe_BNPL_Promotion_Note {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-stripe-bnpl-promotion-note';

	/**
	 * Link to learn more about BNPLs.
	 */
	const LEARN_MORE_LINK = 'https://woocommerce.com/document/stripe/setup-and-configuration/additional-payment-methods/';

	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note_class = self::get_note_class();
		$note       = new $note_class();

		$note->set_title( __( 'Offer more ways to pay with Buy Now, Pay Later', 'woocommerce-gateway-stripe' ) );
		$message  = __( 'Flexible pay-over-time options can boost revenue by up to 14%.* Affirm and Klarna payments are auto-enabled with Stripe for eligible merchants.', 'woocommerce-gateway-stripe' );
		$message .= '<br /><br />';
		$message .= __( '*Source: Stripe 2024', 'woocommerce-gateway-stripe' );
		$note->set_content( $message );
		$note->set_type( $note_class::E_WC_ADMIN_NOTE_MARKETING );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-gateway-stripe' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Learn more', 'woocommerce-gateway-stripe' ),
			self::LEARN_MORE_LINK,
			$note_class::E_WC_ADMIN_NOTE_UNACTIONED,
			true
		);

		return $note;
	}

	/**
	 * Get the class type to be used for the note.
	 *
	 * @return string
	 */
	private static function get_note_class() {
		if ( class_exists( 'Automattic\WooCommerce\Admin\Notes\Note' ) ) {
			return Note::class;
		} else {
			return WC_Admin_Note::class;
		}
	}

	/**
	 * Init BNPL promotion notification
	 *
	 * @param WC_Stripe_Payment_Gateway $gateway
	 *
	 * @return void
	 * @throws \Automattic\WooCommerce\Admin\Notes\NotesUnavailableException
	 */
	public static function init( WC_Stripe_Payment_Gateway $gateway ) {
		/**
		 * No need to display the admin inbox note when
		 * - Below version 9.7
		 * - Store has any BNPLs enabled
		 * - Other BNPL extensions are active
		 * - Stripe is not enabled
		 */
		if ( ! defined( 'WC_STRIPE_VERSION' ) || version_compare( WC_STRIPE_VERSION, '9.7', '<' ) ) {
			return;
		}

		$available_upe_payment_methods = $gateway->get_upe_enabled_payment_method_ids();
		foreach ( WC_Stripe_Payment_Methods::BNPL_PAYMENT_METHODS as $bnpl_payment_method ) {
			if ( in_array( $bnpl_payment_method, $available_upe_payment_methods, true ) ) {
				return;
			}
		}

		if ( WC_Stripe_Helper::has_other_bnpl_plugins_active() ) {
			return;
		}

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_enabled  = isset( $stripe_settings['enabled'] ) && 'yes' === $stripe_settings['enabled'];
		if ( ! $stripe_enabled ) {
			return;
		}

		self::possibly_add_note();
	}
}
