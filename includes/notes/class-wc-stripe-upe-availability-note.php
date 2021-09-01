<?php
/**
 * Display a notice to merchants to inform about UPE.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\NoteTraits;
use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\WC_Admin_Note;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Stripe_UPE_Availability_Note
 */
class WC_Stripe_UPE_Availability_Note {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-stripe-upe-availability-note';

	/**
	 * Link to enable the UPE in store.
	 */
	const ENABLE_IN_STORE_LINK = '?page=wc_stripe-onboarding_wizard';


	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note_class = self::get_note_class();
		$note       = new $note_class();

		$note->set_title( __( 'Boost your sales with the new payment experience in Stripe', 'woocommerce-gateway-stripe' ) );
		$note->set_content( __( 'Get early access to an improved checkout experience, now available to select merchants. <a href="?TODO" target="_blank">Learn more</a>.', 'woocommerce-gateway-stripe' ) );
		$note->set_type( $note_class::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-gateway-stripe' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Enable in your store', 'woocommerce-gateway-stripe' ),
			self::ENABLE_IN_STORE_LINK,
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

	public static function init() {
		self::possibly_add_note();
	}
}
