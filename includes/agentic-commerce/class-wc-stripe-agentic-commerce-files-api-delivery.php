<?php
/**
 * Stripe Files API Delivery Method
 *
 * Handles uploading product feed CSV files to Stripe Files API and creating ImportSets.
 *
 * @package WooCommerce_Stripe
 * @since 10.5.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\ProductFeed\Feed\FeedInterface;

/**
 * Stripe Files API Delivery Method
 *
 * Uploads CSV product feeds to Stripe Files API and creates ImportSets for processing.
 * Implementation will be added in STRIPE-896.
 *
 * @since 10.5.0
 */
class WC_Stripe_Agentic_Commerce_Files_Api_Delivery {

	/**
	 * Stripe secret key.
	 *
	 * @var string
	 */
	private string $secret_key;

	/**
	 * Stripe account ID (optional).
	 *
	 * @var string
	 */
	private string $account_id;

	/**
	 * Initialize delivery method.
	 *
	 * @since 10.5.0
	 * @param string $secret_key Stripe secret key.
	 * @param string $account_id Stripe account ID (optional).
	 */
	public function __construct( string $secret_key, string $account_id = '' ) {
		$this->secret_key = $secret_key;
		$this->account_id = $account_id;
	}

	/**
	 * Check if delivery method is properly configured.
	 *
	 * @since 10.5.0
	 * @return bool True if configured, false otherwise.
	 */
	public function check_setup(): bool {
		return ! empty( $this->secret_key );
	}

	/**
	 * Upload feed to Stripe Files API and create ImportSet.
	 *
	 * @since 10.5.0
	 * @param FeedInterface $feed The feed to deliver.
	 * @return array Response with file_id and import_set_id.
	 * @throws Exception If upload fails.
	 */
	public function deliver( FeedInterface $feed ): array {
		throw new Exception(
			esc_html__( 'Stripe Files API delivery is not yet implemented.', 'woocommerce-gateway-stripe' )
		);
	}
}
