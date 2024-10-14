<?php
/**
 * Class WC_Stripe_UPE_Availability_Note_Test
 *
 * @package WooCommerce_Stripe/Tests/WC_Stripe_UPE_Availability_Note
 */

/**
 * Class WC_Stripe_UPE_Availability_Note tests.
 */
class WC_Stripe_UPE_Availability_Note_Test extends WP_UnitTestCase {
	public function test_get_note() {
		if ( version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			require_once WC_STRIPE_PLUGIN_PATH . '/includes/notes/class-wc-stripe-upe-availability-note.php';
			$note = WC_Stripe_UPE_Availability_Note::get_note();

			$this->assertSame( 'Boost your sales with the new payment experience in Stripe', $note->get_title() );
			$this->assertSame( 'Get early access to an improved checkout experience, now available to select merchants. <a href="https://woocommerce.com/document/stripe/admin-experience/new-checkout-experience/" target="_blank">Learn more</a>.', $note->get_content() );
			$this->assertSame( 'info', $note->get_type() );
			$this->assertSame( 'wc-stripe-upe-availability-note', $note->get_name() );
			$this->assertSame( 'woocommerce-gateway-stripe', $note->get_source() );

			list( $enable_upe_action ) = $note->get_actions();
			$this->assertSame( 'wc-stripe-upe-availability-note', $enable_upe_action->name );
			$this->assertSame( 'Enable in your store', $enable_upe_action->label );
			$this->assertSame( '?page=wc-settings&tab=checkout&section=stripe&panel=settings&highlight=enable-upe', $enable_upe_action->query );
			if ( version_compare( WC_VERSION, '6.5.0', '<' ) ) {
				$this->assertSame( true, $enable_upe_action->primary );
			}
		} else {
			$this->markTestSkipped( 'The used WC components are not backward compatible' );
		}
	}
}
