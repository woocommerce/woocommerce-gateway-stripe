<?php
/**
 * Class Allowed_Payment_Request_Button_Types_Update_Test
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Allowed_Payment_Request_Button_Types_Update unit tests.
 */
class Allowed_Payment_Request_Button_Types_Update_Test extends WP_UnitTestCase {

	/**
	 * Stripe gateway mock.
	 *
	 * @var MockObject|WC_Gateway_Stripe
	 */
	private $gateway_mock;

	/**
	 * @var Allowed_Payment_Request_Button_Types_Update
	 */
	private $migration;

	public function set_up() {
		parent::set_up();

		if ( version_compare( WC_VERSION, '3.4.0', '<' ) ) {
			$this->markTestSkipped( 'The class is not compatible with older WC versions, due to the missing `update_option` method on the gateway.' );
			return;
		}

		$this->gateway_mock = $this->getMockBuilder( WC_Gateway_Stripe::class )
								   ->disableOriginalConstructor()
								   ->getMock();
		$this->migration    = $this->getMockBuilder( Allowed_Payment_Request_Button_Types_Update::class )
								   ->disableOriginalConstructor()
								   ->setMethods( [ 'get_gateway' ] )
								   ->getMock();
	}

	/**
	 * @dataProvider deprecated_values_provider
	 */
	public function test_it_maps_deprecated_button_type_values( string $button_type, string $branded_type, string $expected_mapped_value ) {
		$old_settings = [
			'payment_request_button_type'         => $button_type,
			'payment_request_button_branded_type' => $branded_type,
		];

		$this->setup_environment( $old_settings );
		$this->gateway_mock->expects( $this->once() )
						   ->method( 'update_option' )
						   ->with( 'payment_request_button_type', $expected_mapped_value );

		$this->migration->maybe_migrate();
	}

	/**
	 * @dataProvider not_deprecated_values_provider
	 */
	public function test_it_does_not_map_values_other_than_deprecated( $button_type ) {
		$this->setup_environment( [ 'payment_request_button_type' => $button_type ] );
		$this->gateway_mock->expects( $this->never() )
						   ->method( 'update_option' );

		$this->migration->maybe_migrate();
	}

	private function setup_environment( $settings ) {
		$this->gateway_mock->method( 'get_option' )
						->willReturnCallback(
							function ( $key ) use ( $settings ) {
								return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
							}
						);
		$this->migration->method( 'get_gateway' )->willReturn( $this->gateway_mock );

	}

	public function deprecated_values_provider() {
		return [
			'branded with type = short mapped to default' => [ 'branded', 'short', 'default' ],
			'branded with type != short mapped to buy'    => [ 'branded', 'foo', 'buy' ],
			'branded with missing type mapped to buy'     => [ 'branded', '', 'buy' ],
			'custom mapped to buy'                        => [ 'custom', '', 'buy' ],
		];
	}

	public function not_deprecated_values_provider() {
		return [
			'empty value' => [ '' ],
			[ 'foo' ],
			[ 'default' ],
			[ 'buy' ],
			[ 'donate' ],
		];
	}
}
