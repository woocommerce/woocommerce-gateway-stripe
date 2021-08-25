<?php
/**
 * Display a notice to merchants to inform them that WC Stripe will no longer support older versions of WooCommerce.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\NoteTraits;
use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\WC_Admin_Note;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Stripe_UPE_Compatibility_Note
 */
class WC_Stripe_UPE_Compatibility_Note {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-stripe-upe-wc-compatibility-note';

	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note_class = self::get_note_class();
		$note       = new $note_class();

		$note->set_title( __( 'Important compatibility information about WooCommerce Stripe', 'woocommerce-gateway-stripe' ) );
		$note->set_content( sprintf( __( 'Starting with version 5.6.0, WooCommerce Stripe will require WordPress %1$s or greater and WooCommerce %2$s or greater to be installed and active.', 'woocommerce-gateway-stripe' ), WC_STRIPE_UPE_MIN_WP_VER, WC_STRIPE_UPE_MIN_WC_VER ) );
		$note->set_type( $note_class::E_WC_ADMIN_NOTE_WARNING );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-gateway-stripe' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Learn more', 'woocommerce-gateway-stripe' ),
			'?TODO',
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
		// if the note hasn't been added, add it
		// if it has been added and the merchant has upgraded WC & WP, delete it
		if ( version_compare( WC_VERSION, WC_STRIPE_UPE_MIN_WC_VER, '<' ) || version_compare( get_bloginfo( 'version' ), WC_STRIPE_UPE_MIN_WP_VER, '<' ) ) {
			self::possibly_add_note();
		} else {
			self::possibly_delete_note();
		}
	}
}
