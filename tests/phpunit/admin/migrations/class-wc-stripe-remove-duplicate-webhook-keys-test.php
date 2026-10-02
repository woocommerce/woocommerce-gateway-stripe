<?php

class WC_Stripe_Remove_Duplicate_Webhook_Keys_Test extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		delete_option( 'wc_stripe_removed_duplicate_webhook_keys' );
	}

	/**
	 * Only duplicate credentials can be removed without losing access to old endpoints.
	 *
	 * @dataProvider provide_webhook_credentials
	 */
	public function test_removes_only_duplicate_keys( $webhook_data, $secret_key, $expected ) {
		$settings = [ 'enabled' => 'yes' ];
		foreach ( [ '', 'test_' ] as $prefix ) {
			$settings[ $prefix . 'webhook_data' ]   = $webhook_data;
			$settings[ $prefix . 'secret_key' ]     = $secret_key;
			$settings[ $prefix . 'webhook_secret' ] = 'whsec_signing';
		}
		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		$migration = new WC_Stripe_Remove_Duplicate_Webhook_Keys();
		$migration->maybe_migrate();

		$stored = get_option( 'woocommerce_stripe_settings' );
		foreach ( [ '', 'test_' ] as $prefix ) {
			$this->assertSame( $expected, $stored[ $prefix . 'webhook_data' ] );
			$this->assertSame( $secret_key, $stored[ $prefix . 'secret_key' ] );
			$this->assertSame( 'whsec_signing', $stored[ $prefix . 'webhook_secret' ] );
		}
		$this->assertSame( 'yes', get_option( 'wc_stripe_removed_duplicate_webhook_keys' ) );
	}

	public function provide_webhook_credentials() {
		$data = [
			'id'  => 'we_old',
			'url' => 'https://example.com',
		];
		return [
			'duplicate key'   => [ $data + [ 'secret' => 'sk_current' ], 'sk_current', $data ],
			'different key'   => [ $data + [ 'secret' => 'sk_old' ], 'sk_current', $data + [ 'secret' => 'sk_old' ] ],
			'no account key'  => [ $data + [ 'secret' => 'sk_old' ], '', $data + [ 'secret' => 'sk_old' ] ],
			'already removed' => [ $data, 'sk_current', $data ],
			'empty data'      => [ [], 'sk_current', [] ],
			'invalid data'    => [ '', 'sk_current', '' ],
		];
	}

	/**
	 * A completed migration must not run again after a rollback restores legacy data.
	 */
	public function test_migration_runs_once() {
		update_option( 'wc_stripe_removed_duplicate_webhook_keys', 'yes' );
		$data = [
			'id'     => 'we_old',
			'secret' => 'sk_current',
		];
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'secret_key'   => 'sk_current',
				'webhook_data' => $data,
			]
		);
		( new WC_Stripe_Remove_Duplicate_Webhook_Keys() )->maybe_migrate();
		$this->assertSame( $data, get_option( 'woocommerce_stripe_settings' )['webhook_data'] );
	}
	/**
	 * A failed settings write must leave the migration eligible for another attempt.
	 */
	public function test_failed_write_can_be_retried() {
		WC_Stripe_Helper::update_main_stripe_settings(
			[
				'secret_key'        => 'sk_live_current',
				'webhook_data'      => [
					'id'     => 'we_live',
					'secret' => 'sk_live_current',
				],
				'test_secret_key'   => 'sk_test_current',
				'test_webhook_data' => [
					'id'     => 'we_test',
					'secret' => 'sk_test_old',
				],
			]
		);
		$filter = function ( $value, $old_value ) {
			return $old_value;
		};
		add_filter( 'pre_update_option_woocommerce_stripe_settings', $filter, PHP_INT_MAX, 2 );
		$migration = new WC_Stripe_Remove_Duplicate_Webhook_Keys();
		try {
			$migration->maybe_migrate();
		} finally {
			remove_filter( 'pre_update_option_woocommerce_stripe_settings', $filter, PHP_INT_MAX );
		}
		$this->assertFalse( get_option( 'wc_stripe_removed_duplicate_webhook_keys' ) );
		$migration->maybe_migrate();
		$stored = get_option( 'woocommerce_stripe_settings' );
		$this->assertSame( [ 'id' => 'we_live' ], $stored['webhook_data'] );
		$this->assertSame(
			[
				'id'     => 'we_test',
				'secret' => 'sk_test_old',
			],
			$stored['test_webhook_data']
		);
		$this->assertSame( 'yes', get_option( 'wc_stripe_removed_duplicate_webhook_keys' ) );
	}
}
