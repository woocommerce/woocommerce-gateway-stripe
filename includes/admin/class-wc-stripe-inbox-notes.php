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

	public static function notify_on_apple_pay_domain_verification() {
		if ( ! class_exists( 'Automattic\WooCommerce\Admin\Notes\WC_Admin_Notes' ) ) {
			return;
		}

		if ( ! class_exists( 'WC_Data_Store' ) ) {
			return;
		}

		$stripe_settings       = get_option( 'woocommerce_stripe_settings', array() );
		$domain_flag_key       = 'apple_pay_domain_set';
		$verification_complete = isset( $stripe_settings[ $domain_flag_key ] ) && 'yes' === $stripe_settings[ $domain_flag_key ];

		$data_store = WC_Data_Store::load( 'admin-note' );

		$success_note_ids = $data_store->get_notes_with_name( self::SUCCESS_NOTE_NAME );
		$failure_note_ids = $data_store->get_notes_with_name( self::FAILURE_NOTE_NAME );

		if ( $verification_complete ) {
			if ( empty( $success_note_ids ) ) {
				self::create_success_note();
			}

			if ( ! empty( $failure_note_ids ) ) {
				$note_id = array_pop( $failure_note_ids );
				$note    = WC_Admin_Notes::get_note( $note_id );
				if ( false === $note ) {
					return;
				}

				// If the domain verification completed after failure note was created, make sure it's marked as actioned.
				if ( WC_Admin_Note::E_WC_ADMIN_NOTE_ACTIONED !== $note->get_status() ) {
					$note->set_status( WC_Admin_Note::E_WC_ADMIN_NOTE_ACTIONED );
					$note->save();
				}
			}
		} else {
			if ( empty( $failure_note_ids ) ) {
				self::create_failure_note();
			}
		}
	}

	public static function create_success_note() {
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
