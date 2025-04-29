<?php
/**
 * Class Migrate_Payment_Request_Data_To_Express_Checkout_Data
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Allowed_Payment_Request_Button_Types_Update unit tests.
 */
class Migrate_Payment_Request_Data_To_Express_Checkout_Data_Test extends WP_UnitTestCase {

	/**
	 * Stripe gateway mock.
	 *
	 * @var MockObject|WC_Gateway_Stripe
	 */
	private $gateway_mock;

	/**
	 * @var Migrate_Payment_Request_Data_To_Express_Checkout_Data
	 */
	private $migration;

	public function set_up() {
		parent::set_up();

		$this->gateway_mock = $this->getMockBuilder( WC_Gateway_Stripe::class )
					->disableOriginalConstructor()
					->getMock();
		$this->migration    = $this->getMockBuilder( Migrate_Payment_Request_Data_To_Express_Checkout_Data::class )
					->disableOriginalConstructor()
					->setMethods( [ 'get_gateway' ] )
					->getMock();
	}

	public function test_migration_not_executed_when_data_already_migrated() {
		$this->setup_environment( [ 'express_checkout' => 'yes' ] );

		$this->gateway_mock->expects( $this->never() )
			->method( 'update_option' );

		$this->migration->maybe_migrate();
	}

	public function test_prb_settings_data_migration_to_ece_settings_data() {
		$this->setup_environment( [] );

		$this->gateway_mock->expects( $this->exactly( 5 ) )
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
}
