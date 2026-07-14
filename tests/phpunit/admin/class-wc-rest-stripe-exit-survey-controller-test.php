<?php
/**
 * Class WC_REST_Stripe_Exit_Survey_Controller_Test
 */
class WC_REST_Stripe_Exit_Survey_Controller_Test extends WC_Mock_Stripe_API_Unit_Test_Case {

	/**
	 * Tested REST route.
	 */
	const DISMISS_ROUTE = '/wc/v3/wc_stripe/exit-survey/dismiss';

	/**
	 * Controller instance.
	 *
	 * @var WC_REST_Stripe_Exit_Survey_Controller
	 */
	private $controller;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-stripe-rest-base-controller.php';
		require_once WC_STRIPE_PLUGIN_PATH . '/includes/admin/class-wc-rest-stripe-exit-survey-controller.php';
	}

	public function set_up() {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::set_up();

		$this->controller = new WC_REST_Stripe_Exit_Survey_Controller();

		// Clean up option before each test.
		delete_option( WC_REST_Stripe_Exit_Survey_Controller::OPTION_NAME );
	}

	public function test_dismiss_persists_timestamp() {
		wp_set_current_user( 1 );

		$request  = new WP_REST_Request( 'POST', self::DISMISS_ROUTE );
		$response = $this->controller->dismiss( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		$stored = get_option( WC_REST_Stripe_Exit_Survey_Controller::OPTION_NAME );
		$this->assertNotFalse( $stored );

		// Verify the stored value is a valid ISO 8601 date.
		$date = DateTime::createFromFormat( DateTime::ATOM, $stored );
		$this->assertInstanceOf( DateTime::class, $date );

		// Verify the option is non-autoloaded.
		wp_cache_delete( 'alloptions', 'options' );
		$this->assertArrayNotHasKey(
			WC_REST_Stripe_Exit_Survey_Controller::OPTION_NAME,
			wp_load_alloptions()
		);
	}

	public function test_dismiss_updates_existing_timestamp() {
		wp_set_current_user( 1 );

		update_option( WC_REST_Stripe_Exit_Survey_Controller::OPTION_NAME, '2020-01-01T00:00:00+00:00' );

		$request  = new WP_REST_Request( 'POST', self::DISMISS_ROUTE );
		$response = $this->controller->dismiss( $request );

		$this->assertSame( 200, $response->get_status() );

		$stored = get_option( WC_REST_Stripe_Exit_Survey_Controller::OPTION_NAME );
		$this->assertNotSame( '2020-01-01T00:00:00+00:00', $stored );
	}

	public function test_dismiss_requires_manage_woocommerce_capability() {
		// Use a subscriber user (no manage_woocommerce capability).
		$subscriber_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$result = $this->controller->check_permission();

		$this->assertFalse( $result );
	}

	public function test_dismiss_allows_admin_user() {
		wp_set_current_user( 1 );

		$result = $this->controller->check_permission();

		$this->assertTrue( $result );
	}
}
