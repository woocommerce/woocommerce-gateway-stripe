<?php
/**
 * These teste make assertions against class WC_Stripe_Apple_Pay_Registration.
 *
 * @package WooCommerce_Stripe/Tests/Apple_Pay_Registration
 */

/**
 * WC_Stripe_Apple_Pay_Registration unit tests.
 */
class WC_Stripe_Apple_Pay_Registration_Test extends WP_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Stripe_Apple_Pay_Registration
	 */
	private $wc_apple_pay_registration;

	/**
	 * Domain association file name.
	 *
	 * @var string
	 */
	private $file_name;

	/**
	 * Domain association file contents.
	 *
	 * @var string
	 */
	private $file_contents;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->wc_apple_pay_registration = new WC_Stripe_Apple_Pay_Registration();

		$this->file_name             = 'apple-developer-merchantid-domain-association';
		$this->initial_file_contents = file_get_contents( WC_STRIPE_PLUGIN_PATH . '/' . $this->file_name ); // @codingStandardsIgnoreLine
	}

	public function tear_down() {
		parent::tear_down();

		$path     = untrailingslashit( ABSPATH );
		$dir      = '.well-known';
		$fullpath = $path . '/' . $dir . '/' . $this->file_name;
		// Unlink domain association file before tests.
		@unlink( $fullpath ); // @codingStandardsIgnoreLine
	}

	public function test_update_domain_association_file() {
		$path     = untrailingslashit( ABSPATH );
		$dir      = '.well-known';
		$fullpath = $path . '/' . $dir . '/' . $this->file_name;

		$this->wc_apple_pay_registration->update_domain_association_file();
		$updated_file_contents = file_get_contents( $fullpath ); // @codingStandardsIgnoreLine

		$this->assertEquals( $updated_file_contents, $this->initial_file_contents );
	}

	public function test_add_domain_association_rewrite_rule() {
		$this->set_permalink_structure( '/%postname%/' );
		$this->wc_apple_pay_registration->add_domain_association_rewrite_rule();
		flush_rewrite_rules();

		global $wp_rewrite;
		$rewrite_rule = 'index.php?' . $this->file_name . '=1';

		$this->assertContains( $rewrite_rule, $wp_rewrite->rewrite_rules() );
	}
}
