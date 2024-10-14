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
	const ENABLE_IN_STORE_LINK = '?page=wc-settings&tab=checkout&section=stripe&panel=settings&highlight=enable-upe';


	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note_class = self::get_note_class();
		$note       = new $note_class();

		$note->set_title( __( 'Boost your sales with the new payment experience in Stripe', 'woocommerce-gateway-stripe' ) );
		$message = sprintf(
		/* translators: 1) HTML anchor open tag 2) HTML anchor closing tag */
			__( 'Get early access to an improved checkout experience, now available to select merchants. %1$sLearn more%2$s.', 'woocommerce-gateway-stripe' ),
			'<a href="https://woocommerce.com/document/stripe/admin-experience/new-checkout-experience/" target="_blank">',
			'</a>'
		);
		$note->set_content( $message );
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
		/**
		 * No need to display the admin inbox note when
		 * - UPE preview is disabled
		 * - UPE is already enabled
		 * - UPE has been manually disabled
		 * - Stripe is not enabled
		 */
		if ( ! WC_Stripe_Feature_Flags::is_upe_preview_enabled() ) {
			return;
		}

		if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			return;
		}

		if ( WC_Stripe_Feature_Flags::did_merchant_disable_upe() ) {
			return;
		}

		if ( ! woocommerce_gateway_stripe()->connect->is_connected() ) {
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
