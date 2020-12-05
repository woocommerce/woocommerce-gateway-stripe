<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Admin\Notes\WC_Admin_Note;
use Automattic\WooCommerce\Admin\Notes\WC_Admin_Notes;

/**
 * Class that adds Inbox notifications.
 *
 * @since 4.5.4
 */
class WC_Stripe_Inbox_Notes {
	const SUCCESS_NOTE_NAME = 'stripe-apple-pay-marketing-guide-holiday-2020';
	const FAILURE_NOTE_NAME = 'stripe-apple-pay-domain-verification-needed';

	const POST_SETUP_SUCCESS_ACTION = 'wc_stripe_apple_pay_post_setup_success';

	public function __construct() {
		add_action( self::POST_SETUP_SUCCESS_ACTION, array( self::class, 'create_marketing_note' ) );
	}

	/**
	 * Manage notes to show after Apple Pay domain verification.
	 */
	public static function notify_on_apple_pay_domain_verification( $verification_complete ) {
		if ( ! class_exists( 'Automattic\WooCommerce\Admin\Notes\WC_Admin_Notes' ) ) {
			return;
		}

		if ( ! class_exists( 'WC_Data_Store' ) ) {
			return;
		}

		if ( $verification_complete ) {
			if ( self::should_show_marketing_note() && ! wp_next_scheduled( self::POST_SETUP_SUCCESS_ACTION ) ) {
				wp_schedule_single_event( time() + DAY_IN_SECONDS, self::POST_SETUP_SUCCESS_ACTION );
			}

			// If the domain verification completed after failure note was created, make sure it's marked as actioned.
			try {
				$data_store       = WC_Data_Store::load( 'admin-note' );
				$failure_note_ids = $data_store->get_notes_with_name( self::FAILURE_NOTE_NAME );
				if ( ! empty( $failure_note_ids ) ) {
					$note_id = array_pop( $failure_note_ids );
					$note    = WC_Admin_Notes::get_note( $note_id );
					if ( false !== $note && WC_Admin_Note::E_WC_ADMIN_NOTE_ACTIONED !== $note->get_status() ) {
						$note->set_status( WC_Admin_Note::E_WC_ADMIN_NOTE_ACTIONED );
						$note->save();
					}
				}
			} catch ( Exception $e ) {}  // @codingStandardsIgnoreLine.
		} else {
			if ( empty( $failure_note_ids ) ) {
				self::create_failure_note();
			}
		}
	}

	/**
	 * Whether conditions are right for the marketing note.
	 */
	public static function should_show_marketing_note() {
		// Display to US merchants only.
		$base_location = wc_get_base_location();
		if ( ! $base_location || 'US' !== $base_location['country'] ) {
			return false;
		}

		// Make sure Apple Pay is enabled and setup is successful.
		$stripe_settings       = get_option( 'woocommerce_stripe_settings', array() );
		$stripe_enabled        = isset( $stripe_settings['enabled'] ) && 'yes' === $stripe_settings['enabled'];
		$button_enabled        = isset( $stripe_settings['payment_request'] ) && 'yes' === $stripe_settings['payment_request'];
		$verification_complete = isset( $stripe_settings['apple_pay_domain_set'] ) && 'yes' === $stripe_settings['apple_pay_domain_set'];
		if ( ! $stripe_enabled || ! $button_enabled || ! $verification_complete ) {
			return false;
		}

		// Make sure note doesn't already exist.
		try {
			$data_store       = WC_Data_Store::load( 'admin-note' );
			$success_note_ids = $data_store->get_notes_with_name( self::SUCCESS_NOTE_NAME );
			if ( ! empty( $success_note_ids ) ) {
				return false;
			}
		} catch ( Exception $e ) {
			return false; // If unable to check, assume it shouldn't show note.
		}

		return true;
	}

	/**
	 * If conditions are right, show note promoting Apple Pay marketing guide.
	 */
	public static function create_marketing_note() {
		// Make sure conditions for this note still hold.
		if ( ! self::should_show_marketing_note() ) {
			return;
		}

		$note = new WC_Admin_Note();
		$note->set_title( __( 'Boost sales this holiday season with Apple Pay!', 'woocommerce-gateway-stripe' ) );
		$note->set_content( __( 'Now that you accept Apple Pay® with Stripe, you can increase conversion rates by letting your customers know that Apple Pay is available. Here’s a marketing guide to help you get started.', 'woocommerce-gateway-stripe' ) );
		$note->set_type( WC_Admin_Note::E_WC_ADMIN_NOTE_MARKETING );
		$note->set_name( self::SUCCESS_NOTE_NAME );
		$note->set_source( 'woocommerce-gateway-stripe' );
		$note->add_action(
			'marketing-guide',
			__( 'See marketing guide', 'woocommerce-gateway-stripe' ),
			'https://developer.apple.com/apple-pay/marketing/'
		);
		$note->save();
	}

	/**
	 * Show note indicating domain verification failure.
	 */
	public static function create_failure_note() {
		$note = new WC_Admin_Note();
		$note->set_title( __( 'Apple Pay domain verification needed', 'woocommerce-gateway-stripe' ) );
		$note->set_content( __( 'The WooCommerce Stripe Gateway extension attempted to perform domain verification on behalf of your store, but was unable to do so. This must be resolved before Apple Pay can be offered to your customers.', 'woocommerce-gateway-stripe' ) );
		$note->set_type( WC_Admin_Note::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::FAILURE_NOTE_NAME );
		$note->set_source( 'woocommerce-gateway-stripe' );
		$note->add_action(
			'learn-more',
			__( 'Learn more', 'woocommerce-gateway-stripe' ),
			'https://docs.woocommerce.com/document/stripe/#apple-pay'
		);
		$note->save();
	}
}

new WC_Stripe_Inbox_Notes();
