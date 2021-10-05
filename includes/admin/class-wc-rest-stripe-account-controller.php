<?php
/**
 * Class WC_REST_Stripe_Account_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for retrieving Stripe's account data.
 *
 * @since 5.6.0
 */
class WC_REST_Stripe_Account_Controller extends WC_Stripe_REST_Base_Controller {
	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_stripe/account';

	/**
	 * The account data utility.
	 *
	 * @var WC_Stripe_Account
	 */
	private $account;

	public function __construct( WC_Stripe_Account $account ) {
		$this->account = $account;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_account' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/refresh',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'refresh_account' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve the Stripe account information.
	 *
	 * @return WP_REST_Response
	 */
	public function get_account() {
		return new WP_REST_Response(
			[
				'account' => $this->account->get_cached_account_data(),
			]
		);
	}

	/**
	 * Clears the cached account data and returns the updated one.
	 *
	 * @return WP_REST_Response
	 */
	public function refresh_account() {
		$this->account->clear_cache();

		// calling the same "get" method, so that the data format is the same.
		return $this->get_account();
	}
}
