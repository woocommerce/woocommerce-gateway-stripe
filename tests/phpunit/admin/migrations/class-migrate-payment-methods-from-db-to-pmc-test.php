<?php

/**
 * Legacy initialization must not union Stripe methods into the merchant's choices.
 */
class Migrate_Payment_Methods_From_DB_To_PMC_Test extends WC_Mock_Stripe_API_Unit_Test_Case {
	/**
	 * @dataProvider initialization_provider
	 */
	public function test_initialization_preserves_selections( string $flag, bool $force, array $methods ): void {
		$settings = [
			'testmode'                                  => 'yes',
			'test_publishable_key'                      => 'pk_test_example',
			'test_secret_key'                           => 'sk_test_example',
			'pmc_enabled'                               => $flag,
			'upe_checkout_experience_accepted_payments' => $methods,
			'express_checkout'                          => 'no',
		];
		$this->mock_payment_method_configurations( [ 'card' ] );
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		$this->stripe_api->expects( $this->never() )->method( 'update_payment_method_configurations' );
		WC_Stripe_Payment_Method_Configurations::maybe_migrate_payment_methods_from_db_to_pmc( $force );
		$this->assertSame( array_merge( $settings, [ 'pmc_enabled' => '' === $flag ? 'no' : $flag ] ), WC_Stripe_Helper::get_stripe_settings() );
	}

	public function initialization_provider(): array {
		return [
			'active'          => [ 'yes', false, [ 'card' ] ],
			'forced active'   => [ 'yes', true, [ 'card' ] ],
			'disabled'        => [ 'no', true, [ 'card', 'klarna' ] ],
			'new connection'  => [ '', false, [ 'card' ] ],
			'empty selection' => [ '', true, [] ],
		];
	}
}
