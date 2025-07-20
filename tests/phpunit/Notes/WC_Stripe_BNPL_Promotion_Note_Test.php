<?php

namespace WooCommerce\Stripe\Tests\Notes;

use WC_Stripe_BNPL_Promotion_Note;
use WP_UnitTestCase;

/**
 * Class WC_Stripe_BNPL_Promotion_Note_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_BNPL_Promotion_Note
 *
 * Class WC_Stripe_BNPL_Promotion_Note tests.
 */
class WC_Stripe_BNPL_Promotion_Note_Test extends WP_UnitTestCase {
	public function test_get_note() {
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-bnpl-promotion-note.php';
		$note = WC_Stripe_BNPL_Promotion_Note::get_note();

		$this->assertSame( 'Offer more ways to pay with Buy Now, Pay Later', $note->get_title() );
		$this->assertSame( 'Flexible pay-over-time options can boost revenue by up to 14%.* Affirm and Klarna payments are auto-enabled with Stripe for eligible merchants.<br /><br />*Source: Stripe 2024', $note->get_content() );
		$this->assertSame( 'marketing', $note->get_type() );
		$this->assertSame( 'wc-stripe-bnpl-promotion-note', $note->get_name() );
		$this->assertSame( 'woocommerce-gateway-stripe', $note->get_source() );

		list( $learn_more_action ) = $note->get_actions();
		$this->assertSame( 'wc-stripe-bnpl-promotion-note', $learn_more_action->name );
		$this->assertSame( 'Learn more', $learn_more_action->label );
		$this->assertSame( 'https://woocommerce.com/document/stripe/setup-and-configuration/additional-payment-methods/', $learn_more_action->query );
	}
}
