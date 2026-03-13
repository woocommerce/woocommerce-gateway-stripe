<?php

/**
 * Class WC_Stripe_OC_Promotion_Note_Test
 *
 * @package WooCommerce/Stripe/WC_Stripe_OC_Promotion_Note
 *
 * Class WC_Stripe_OC_Promotion_Note tests.
 */
class WC_Stripe_OC_Promotion_Note_Test extends WP_UnitTestCase {
	public function test_get_note() {
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-oc-promotion-note.php';
		$note = WC_Stripe_OC_Promotion_Note::get_note();

		$this->assertSame( 'Increase conversions with Stripe\'s Optimized Checkout Suite', $note->get_title() );
		$this->assertSame( 'Optimize your checkout for more sales by automatically displaying the most relevant payment methods for each customer.', $note->get_content() );
		$this->assertSame( 'marketing', $note->get_type() );
		$this->assertSame( 'wc-stripe-oc-promotion-note', $note->get_name() );
		$this->assertSame( 'woocommerce-gateway-stripe', $note->get_source() );

		list( $learn_more_action ) = $note->get_actions();
		$this->assertSame( 'wc-stripe-oc-promotion-note', $learn_more_action->name );
		$this->assertSame( 'Activate now', $learn_more_action->label );
		$this->assertSame( '?page=wc-settings&tab=checkout&section=stripe&panel=settings&highlight=enable-optimized-checkout', $learn_more_action->query );
	}
}
