<?php

/**
 * Class WC_Stripe_Capture_Method_Trait_Test
 */
class WC_Stripe_Capture_Method_Trait_Test extends WP_UnitTestCase {
	/**
	 * Test for `is_automatic_capture_enabled` method.
	 *
	 * @return void
	 */
	public function test_is_automatic_capture_enabled() {
		$class = $this->get_mocked_class( 'yes' );
		$this->assertTrue( $class->is_automatic_capture_enabled() );

		$class = $this->get_mocked_class( 'no' );
		$this->assertFalse( $class->is_automatic_capture_enabled() );

		$class = $this->get_mocked_class( '' );
		$this->assertTrue( $class->is_automatic_capture_enabled() );
	}

	/**
	 * Mocks a class using the tested trait.
	 *
	 * @return mixed
	 */
	private function get_mocked_class( $option_value ) {
		return new class( $option_value ) {
			use WC_Stripe_Capture_Method_Trait;

			private string $option_value;

			public function __construct( $option_value ) {
				$this->option_value = $option_value;
			}

			public function get_option() {
				return $this->option_value;
			}
		};
	}
}
