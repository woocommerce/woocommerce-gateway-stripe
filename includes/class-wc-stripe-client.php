<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Stripe\StripeClient;

/**
 * WC_Stripe_Client class.
 */
class WC_Stripe_Client {
	public static ?StripeClient $sdk = null;
	private static ?WC_Stripe_Client $instance = null;

	public function __construct( StripeClient $sdk ) {
		self::$sdk = $sdk;
	}

	public static function get_instance(): ?self {
		if ( is_null( self::$instance ) ) {
			$settings       = WC_Stripe_Helper::get_stripe_settings();
			$setting_key    = WC_Stripe_Mode::is_test() ? 'test_secret_key' : 'secret_key';
			$sdk            = new StripeClient( $settings[ $setting_key ] ?? '' );
			self::$instance = new self( $sdk );
		}

		return self::$instance;
	}

	public static function set_instance( ?self $instance ) {
		self::$instance = $instance;
	}
}
