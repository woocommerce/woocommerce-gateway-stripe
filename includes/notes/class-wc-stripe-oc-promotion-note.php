<?php
/**
 * Display a notice to merchants to promote OC (Optimized Checkout).
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\NoteTraits;
use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\WC_Admin_Note;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Stripe_OC_Promotion_Note
 */
final class WC_Stripe_OC_Promotion_Note {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-stripe-oc-promotion-note';

	/**
	 * Link to activate OC in store.
	 */
	private const ACTIVATE_NOW_LINK = '?page=wc-settings&tab=checkout&section=stripe&panel=settings&highlight=enable-optimized-checkout';

	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note_class = self::get_note_class();
		$note       = new $note_class();

		$note->set_title( __( 'Increase conversions with Stripe\'s Optimized Checkout Suite', 'woocommerce-gateway-stripe' ) );
		$note->set_content( __( 'Optimize your checkout for more sales by automatically displaying the most relevant payment methods for each customer.', 'woocommerce-gateway-stripe' ) );
		$note->set_type( $note_class::E_WC_ADMIN_NOTE_MARKETING );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-gateway-stripe' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Activate now', 'woocommerce-gateway-stripe' ),
			self::ACTIVATE_NOW_LINK,
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
	 * Init OC promotion notification
	 *
	 * @param WC_Stripe_Payment_Gateway $gateway
	 *
	 * @return void
	 * @throws \Automattic\WooCommerce\Admin\Notes\NotesUnavailableException
	 */
	public static function init( WC_Stripe_Payment_Gateway $gateway ) {
		/**
		 * No need to display the admin inbox note when
		 * - Below version 9.8
		 * - OC is already enabled
		 * - Stripe is not enabled
		 */
		if ( ! defined( 'WC_STRIPE_VERSION' ) || version_compare( WC_STRIPE_VERSION, '9.8', '<' ) ) {
			return;
		}

		if ( $gateway->is_oc_enabled() ) {
			return;
		}

		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();
		$stripe_enabled  = isset( $stripe_settings['enabled'] ) && 'yes' === $stripe_settings['enabled'];
		if ( ! $stripe_enabled ) {
			return;
		}

		self::possibly_add_note();
	}

	/**
	 * Should this note exist?
	 *
	 * @inheritDoc
	 */
	public static function is_applicable() {
		return ! WC_Stripe::get_instance()->get_main_stripe_gateway()->is_oc_enabled();
	}
}
