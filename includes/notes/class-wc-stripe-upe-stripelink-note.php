<?php
/**
 * Display a notice to merchants to inform about Stripe Link.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\NoteTraits;
use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\WC_Admin_Note;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Stripe_UPE_StripeLink_Note
 */
class WC_Stripe_UPE_StripeLink_Note {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-stripe-upe-stripelink-note';

	/**
	 * Link to Stripe Link documentation.
	 */
	const NOTE_DOCUMENTATION_URL = 'https://woocommerce.com/document/stripe/setup-and-configuration/express-checkouts/';

	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note_class = self::get_note_class();
		$note       = new $note_class();

		$note->set_title( __( 'Increase conversion at checkout', 'woocommerce-gateway-stripe' ) );
		$note->set_content( __( 'Reduce cart abandonment and create a frictionless checkout experience with Link by Stripe. Link autofills your customer’s payment and shipping details so they can check out in just six seconds with the Link optimized experience.', 'woocommerce-gateway-stripe' ) );

		$note->set_type( $note_class::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-gateway-stripe' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Set up now', 'woocommerce-gateway-stripe' ),
			self::NOTE_DOCUMENTATION_URL,
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
	 * Init Link payment method notification
	 *
	 * @param WC_Stripe_Payment_Gateway $gateway
	 *
	 * @return void
	 * @throws \Automattic\WooCommerce\Admin\Notes\NotesUnavailableException
	 */
	public static function init( WC_Stripe_Payment_Gateway $gateway ) {
		if ( ! WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
			return;
		}

		// Check if Link payment is available.
		$available_upe_payment_methods = $gateway->get_upe_available_payment_methods();

		if ( ! in_array( WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID, $available_upe_payment_methods, true ) ) {
			return;
		}

		if ( ! is_a( $gateway, 'WC_Stripe_UPE_Payment_Gateway' ) ) {
			return;
		}

		// If store currency is not USD, skip
		if ( 'USD' !== get_woocommerce_currency() ) {
			return;
		}

		// Retrieve enabled payment methods at checkout.
		$enabled_payment_methods = $gateway->get_upe_enabled_at_checkout_payment_method_ids();
		// If card payment method is not enabled, skip. If Link payment method is enabled, skip.
		if (
			! in_array( WC_Stripe_UPE_Payment_Method_CC::STRIPE_ID, $enabled_payment_methods, true ) ||
			in_array( WC_Stripe_UPE_Payment_Method_Link::STRIPE_ID, $enabled_payment_methods, true )
		) {
			return;
		}

		self::possibly_add_note();
	}
}
