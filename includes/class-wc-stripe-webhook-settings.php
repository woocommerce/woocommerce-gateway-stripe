<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Webhook settings and automatic endpoint configuration.
 *
 * @since x.x.x
 */
class WC_Stripe_Webhook_Settings {
	/**
	 * List of webhook events that this plugin listens to.
	 * Based on WC_Stripe_Webhook_Handler::process_webhook()
	 *
	 * @since x.x.x
	 */
	public const WEBHOOK_EVENTS = [
		'account.updated',
		'source.chargeable',
		'source.canceled',
		'charge.succeeded',
		'charge.failed',
		'charge.captured',
		'charge.dispute.created',
		'charge.dispute.closed',
		'charge.refunded',
		'charge.refund.updated',
		'review.opened',
		'review.closed',
		'payment_intent.processing',
		'payment_intent.succeeded',
		'payment_intent.payment_failed',
		'payment_intent.amount_capturable_updated',
		'payment_intent.requires_action',
		'setup_intent.succeeded',
		'setup_intent.setup_failed',
		'checkout.session.completed',
		'checkout.session.expired',
		'checkout.session.async_payment_succeeded',
		'checkout.session.async_payment_failed',
	];

	/**
	 * The webhook status cache key.
	 *
	 * @since x.x.x
	 *
	 * @internal
	 *
	 * @var string
	 */
	public const WEBHOOK_STATUS_CACHE_KEY = 'webhook_status';

	/** Metadata stamped on endpoints this plugin creates, so cleanup can tell them from merchant-created ones. */
	protected const WEBHOOK_METADATA_CREATED_BY_KEY   = 'created_by';
	protected const WEBHOOK_METADATA_CREATED_BY_VALUE = 'woocommerce_gateway_stripe';

	/** Option flagging that reconfiguration was skipped over a manually set signing secret. */
	private const WEBHOOK_MANUAL_SECRET_NOTICE_OPTION = 'wc_stripe_show_webhook_manual_secret_notice';

	/** Option flagging that the stored webhook endpoint no longer exists in Stripe. */
	private const WEBHOOK_MISSING_NOTICE_OPTION = 'wc_stripe_show_webhook_missing_notice';

	/**
	 * Stripe API implementation.
	 *
	 * @var class-string<WC_Stripe_API>
	 */
	private $stripe_api;

	/**
	 * Constructor.
	 *
	 * @since x.x.x
	 *
	 * @param class-string<WC_Stripe_API> $stripe_api Stripe API implementation.
	 */
	public function __construct( $stripe_api ) {
		$this->stripe_api = $stripe_api;
	}

	/**
	 * Option name for the "webhook endpoint missing" notice flag.
	 *
	 * @since x.x.x
	 *
	 * @param string $mode 'live' or 'test'. Any other value is treated as 'test'.
	 * @return string
	 */
	public static function get_webhook_missing_notice_option( string $mode ): string {
		return self::get_webhook_notice_option( self::WEBHOOK_MISSING_NOTICE_OPTION, $mode );
	}

	/**
	 * Option name for the "reconfiguration skipped over a manual secret" notice flag.
	 *
	 * @since x.x.x
	 *
	 * @param string $mode 'live' or 'test'. Any other value is treated as 'test'.
	 * @return string
	 */
	public static function get_webhook_manual_secret_notice_option( string $mode ): string {
		return self::get_webhook_notice_option( self::WEBHOOK_MANUAL_SECRET_NOTICE_OPTION, $mode );
	}

	private static function get_webhook_notice_option( string $base, string $mode ): string {
		return $base . ( 'live' === $mode ? '_live' : '_test' );
	}

	/**
	 * Ensures the selected mode has a usable endpoint with the plugin configuration.
	 *
	 * @since x.x.x
	 *
	 * @param string $mode  Either 'live' or 'test'.
	 * @param bool   $force Replace even a healthy endpoint, returning its new signing secret.
	 * @return ($force is true ? stdClass : stdClass|null) Created endpoint, or null when configuration is unnecessary or automatic setup is skipped.
	 * @throws Exception When the endpoint cannot be checked or configured.
	 */
	public function maybe_autoconfigure_webhooks( string $mode, bool $force = false ): ?stdClass {
		if ( ! in_array( $mode, [ 'live', 'test' ], true ) ) {
			throw new InvalidArgumentException( 'Invalid webhook mode.' );
		}

		$settings   = WC_Stripe_Helper::get_stripe_settings();
		$prefix     = 'test' === $mode ? 'test_' : '';
		$secret_key = $settings[ $prefix . 'secret_key' ] ?? '';
		if ( empty( $secret_key ) ) {
			if ( $force ) {
				throw new Exception( __( 'There was a problem setting up your webhooks, please try again later.', 'woocommerce-gateway-stripe' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			return null;
		}

		$previous_secret = WC_Stripe_API::get_secret_key();
		try {
			WC_Stripe_API::set_secret_key( $secret_key );
			$webhook_data  = is_array( $settings[ $prefix . 'webhook_data' ] ?? null ) ? $settings[ $prefix . 'webhook_data' ] : [];
			$webhook_id    = $webhook_data['id'] ?? '';
			$stored_secret = $settings[ $prefix . 'webhook_secret' ] ?? '';
			if ( ! $force && $webhook_id ) {
				$webhook = $this->stripe_api::request( [], 'webhook_endpoints/' . $webhook_id, 'GET' );
				if ( isset( $webhook->error ) ) {
					if ( 'resource_missing' !== ( $webhook->error->code ?? '' ) ) {
						throw new Exception( __( 'There was a problem checking your webhooks, please try again later.', 'woocommerce-gateway-stripe' ) );
					}
					$webhook_id = '';
					update_option( self::get_webhook_missing_notice_option( $mode ), 'yes' );
				} elseif ( ! isset( $webhook->id, $webhook->url, $webhook->enabled_events, $webhook->status ) || ! property_exists( $webhook, 'api_version' ) || ! is_array( $webhook->enabled_events ) ) {
					throw new Exception( __( 'There was a problem checking your webhooks, please try again later.', 'woocommerce-gateway-stripe' ) );
				} else {
					// A staging clone can carry production's endpoint ID; automatic setup must leave that site alone.
					if ( ! WC_Stripe_Helper::is_webhook_url( $webhook->url ) ) {
						return null;
					}

					$desired_events  = self::WEBHOOK_EVENTS;
					$existing_events = $webhook->enabled_events;
					sort( $desired_events );
					sort( $existing_events );
					if (
						WC_Stripe_Helper::get_webhook_url() === $webhook->url
						&& $desired_events === $existing_events
						&& self::get_webhooks_api_version() === $webhook->api_version
						&& 'enabled' === $webhook->status
						&& ! empty( $settings[ $prefix . 'webhook_secret' ] )
					) {
						WC_Stripe_Database_Cache::set_with_mode( self::WEBHOOK_STATUS_CACHE_KEY, 'enabled', 2 * HOUR_IN_SECONDS, $mode );
						return null;
					}
				}
			}

			if ( ! $force ) {
				// Legacy records have no signing_secret; only a known manual edit blocks automatic setup.
				$secret_is_manual = '' !== $stored_secret && (
					empty( $webhook_data['id'] )
					|| ( isset( $webhook_data['signing_secret'] ) && $webhook_data['signing_secret'] !== $stored_secret )
				);
				if ( $secret_is_manual ) {
					update_option( self::get_webhook_manual_secret_notice_option( $mode ), 'yes' );
					WC_Stripe_Logger::info( "Skipped automatic webhook reconfiguration for {$mode} mode: the stored signing secret was set manually." );
					return null;
				}

				if ( ! $webhook_id ) {
					// Leave a notice visible if creating the missing endpoint fails.
					update_option( self::get_webhook_missing_notice_option( $mode ), 'yes' );
				}
			}

			return $this->configure_webhooks( $mode, $webhook_id );
		} finally {
			WC_Stripe_API::set_secret_key( $previous_secret );
		}
	}

	/**
	 * Cleans up endpoints before their account keys are replaced or removed.
	 *
	 * @since x.x.x
	 *
	 * @param array    $previous_settings Settings before replacing the account keys.
	 * @param array    $settings          Candidate settings; the caller persists the result.
	 * @param string[] $modes             Modes whose keys are being saved.
	 * @return array
	 */
	public function decommission_for_key_change( array $previous_settings, array $settings, array $modes ): array {
		foreach ( $modes as $mode ) {
			$prefix       = 'test' === $mode ? 'test_' : '';
			$webhook_data = $settings[ $prefix . 'webhook_data' ] ?? [];
			$new_secret   = $settings[ $prefix . 'secret_key' ] ?? '';
			if ( is_array( $webhook_data ) && empty( $webhook_data['secret'] ) ) {
				$webhook_data['secret'] = $previous_settings[ $prefix . 'secret_key' ] ?? '';
			}

			if ( $this->maybe_decommission_webhook( $webhook_data, $new_secret ) ) {
				$settings[ $prefix . 'webhook_data' ]   = [];
				$settings[ $prefix . 'webhook_secret' ] = '';
			} elseif ( $this->should_decommission_webhook( $webhook_data, $new_secret ) ) {
				// Retain the original account key when the endpoint could not be deleted.
				$settings[ $prefix . 'webhook_data' ] = $webhook_data;
			}
		}

		return $settings;
	}

	/**
	 * Configures webhooks for the account.
	 *
	 * @param string $mode Either 'live' or 'test'.
	 * @param string $previous_webhook_id Stored endpoint to replace.
	 *
	 * @throws Exception If there was a problem setting up the webhooks.
	 * @return stdClass The response from the API.
	 */
	private function configure_webhooks( string $mode, string $previous_webhook_id ) {

		$request = [
			'enabled_events' => self::WEBHOOK_EVENTS,
			'url'            => WC_Stripe_Helper::get_webhook_url(),
			'api_version'    => self::get_webhooks_api_version(),
			'metadata'       => [ self::WEBHOOK_METADATA_CREATED_BY_KEY => self::WEBHOOK_METADATA_CREATED_BY_VALUE ],
		];

		$response = $this->stripe_api::request( $request, 'webhook_endpoints', 'POST' );

		if ( isset( $response->error->message ) ) {
			// Translators: %s is the error message from the Stripe API.
			throw new Exception( sprintf( __( 'There was a problem setting up your webhooks. %s', 'woocommerce-gateway-stripe' ), $response->error->message ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! isset( $response->secret, $response->id, $response->url ) ) {
			throw new Exception( __( 'There was a problem setting up your webhooks, please try again later.', 'woocommerce-gateway-stripe' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$settings = WC_Stripe_Helper::get_stripe_settings();

		$webhook_secret_setting = 'live' === $mode ? 'webhook_secret' : 'test_webhook_secret';
		$webhook_data_setting   = 'live' === $mode ? 'webhook_data' : 'test_webhook_data';

		// Save the Webhook secret and ID.
		$settings[ $webhook_secret_setting ] = wc_clean( $response->secret );
		$settings[ $webhook_data_setting ]   = [
			'id'             => wc_clean( $response->id ),
			'url'            => wc_clean( $response->url ),
			'secret'         => WC_Stripe_API::get_secret_key(),
			// Lets automatic setup distinguish a plugin-written secret from a manually pasted one.
			'signing_secret' => wc_clean( $response->secret ),
		];

		WC_Stripe_Helper::update_main_stripe_settings( $settings );
		delete_option( self::get_webhook_manual_secret_notice_option( $mode ) );
		delete_option( self::get_webhook_missing_notice_option( $mode ) );

		WC_Stripe_Database_Cache::delete_with_mode( self::WEBHOOK_STATUS_CACHE_KEY, $mode );
		WC_Stripe_Webhook_State::clear_state( $mode );

		// Stripe only returns the signing secret at creation; persist it before removing the old endpoint.
		try {
			$this->delete_previously_configured_webhooks( $response->id, $previous_webhook_id, $mode );
		} catch ( Exception $e ) {
			// Configuration is already saved; failed cleanup must not turn a working connection into an error.
			WC_Stripe_Logger::error( 'Unable to delete previously configured webhooks', [ 'error_message' => $e->getMessage() ] );
		}

		return $response;
	}

	/**
	 * Deletes any previously configured webhooks that are sent to the current site's webhook URL.
	 *
	 * @param string $exclude_webhook_id Webhook ID to exclude from deletion.
	 * @param string $previous_webhook_id Stored endpoint to replace.
	 * @param string $mode Either 'live' or 'test', for logging.
	 */
	private function delete_previously_configured_webhooks( string $exclude_webhook_id, string $previous_webhook_id, string $mode ): void {
		$webhooks = $this->stripe_api::retrieve( 'webhook_endpoints' );

		if ( is_wp_error( $webhooks ) || ! isset( $webhooks->data ) || empty( $webhooks->data ) ) {
			return;
		}

		$webhook_url = WC_Stripe_Helper::get_webhook_url();
		$log_context = [ 'mode' => $mode ];

		WC_Stripe_Logger::info(
			"Deleting this plugin's {$mode}-mode webhooks sent to {$webhook_url} except for {$exclude_webhook_id}",
			$log_context
		);

		// The previous ID was overwritten when the new secret was saved, and may predate endpoint metadata.
		$settings            = WC_Stripe_Helper::get_stripe_settings();
		$plugin_recorded_ids = array_filter(
			[
				$previous_webhook_id,
				$settings['webhook_data']['id'] ?? '',
				$settings['test_webhook_data']['id'] ?? '',
			]
		);

		foreach ( $webhooks->data as $webhook ) {
			if ( ! isset( $webhook->id, $webhook->url ) ) {
				continue;
			}

			// Skip the webhook we're excluding from deletion.
			if ( $webhook->id === $exclude_webhook_id ) {
				continue;
			}

			if ( ! WC_Stripe_Helper::is_webhook_url( $webhook->url, $webhook_url ) ) {
				continue;
			}

			$is_plugin_created = in_array( $webhook->id, $plugin_recorded_ids, true )
				|| self::WEBHOOK_METADATA_CREATED_BY_VALUE === ( $webhook->metadata->{self::WEBHOOK_METADATA_CREATED_BY_KEY} ?? '' );
			if ( ! $is_plugin_created ) {
				WC_Stripe_Logger::info( "Skipped deleting webhook {$webhook->id}: it points at this site's webhook URL but was not created by this plugin.", $log_context );
				continue;
			}

			$response = $this->stripe_api::request( [], "webhook_endpoints/{$webhook->id}", 'DELETE' );
			if ( isset( $response->error ) && 'resource_missing' !== ( $response->error->code ?? '' ) ) {
				WC_Stripe_Logger::error(
					"Failed to delete previously configured webhook {$webhook->id}.",
					[ 'error_code' => is_string( $response->error->code ?? null ) ? $response->error->code : 'unknown' ]
				);
				continue;
			}
			WC_Stripe_Logger::info( "Deleted webhook {$webhook->id} because this plugin created it for this site's webhook URL.", $log_context );
		}
	}

	/**
	 * Shares eligibility between deletion and retention after a failed attempt.
	 *
	 * @internal
	 * @param mixed  $webhook_data Stored endpoint data.
	 * @param string $new_secret_key Account API key being saved, or empty when disconnecting.
	 */
	private function should_decommission_webhook( $webhook_data, $new_secret_key ): bool {
		if ( ! is_array( $webhook_data ) || empty( $webhook_data['id'] ) || empty( $webhook_data['secret'] ) ) {
			return false;
		}

		return empty( $new_secret_key ) || $new_secret_key !== $webhook_data['secret'];
	}

	/**
	 * Decommissions a previously configured webhook endpoint when the secret key that
	 * created it is being removed or replaced.
	 *
	 * @param mixed  $webhook_data   The previously stored webhook data. Expected to contain 'id' and 'secret'.
	 * @param string $new_secret_key The secret key that is about to be saved. Empty when disconnecting.
	 *
	 * @return bool True if a webhook was decommissioned, false otherwise.
	 */
	private function maybe_decommission_webhook( $webhook_data, $new_secret_key ): bool {
		if ( ! $this->should_decommission_webhook( $webhook_data, $new_secret_key ) ) {
			return false;
		}

		$previous_secret = WC_Stripe_API::get_secret_key();
		try {
			// Authenticate with the secret key that created the webhook so the deletion
			// hits the originally connected account.
			WC_Stripe_API::set_secret_key( $webhook_data['secret'] );
			$response = $this->stripe_api::request( [], 'webhook_endpoints/' . $webhook_data['id'], 'DELETE' );

			if ( isset( $response->error ) && 'resource_missing' !== ( $response->error->code ?? '' ) ) {
				// Stripe returns failed DELETE requests as objects; only an already missing endpoint needs no retry.
				WC_Stripe_Logger::error(
					"Failed to decommission previously configured webhook {$webhook_data['id']}.",
					[ 'error_code' => is_string( $response->error->code ?? null ) ? $response->error->code : 'unknown' ]
				);
				return false;
			}

			WC_Stripe_Logger::info( "Decommissioned previously configured webhook {$webhook_data['id']} before saving new keys." );
		} catch ( Exception $e ) {
			// A failure here must not abort the connection flow, so we log and report that nothing was decommissioned.
			WC_Stripe_Logger::error(
				"Failed to decommission previously configured webhook {$webhook_data['id']}.",
				[ 'error_message' => $e->getMessage() ]
			);
			return false;
		} finally {
			WC_Stripe_API::set_secret_key( $previous_secret );
		}

		return true;
	}

	/**
	 * Determine if the webhook is enabled by checking with Stripe.
	 *
	 * @since x.x.x
	 *
	 * @param string|null $mode Mode to check, or null for the current mode.
	 * @return bool
	 */
	public function is_webhook_enabled( $mode = null ) {
		$stripe_settings  = WC_Stripe_Helper::get_stripe_settings();
		$mode             = $mode ?? ( WC_Stripe_Mode::is_test() ? 'test' : 'live' );
		$is_testmode      = 'test' === $mode;
		$webhook_data_key = $is_testmode ? 'test_webhook_data' : 'webhook_data';
		$webhook_data     = $stripe_settings[ $webhook_data_key ] ?? [];
		$secret_key       = $stripe_settings[ $is_testmode ? 'test_secret_key' : 'secret_key' ] ?? '';
		if ( ! empty( $webhook_data['secret'] ) ) {
			$secret_key = $webhook_data['secret'];
		}

		if ( empty( $webhook_data['id'] ) || empty( $secret_key ) ) {
			return false;
		}

		// Check if we have a cached status.
		$cached_status = WC_Stripe_Database_Cache::get_with_mode( self::WEBHOOK_STATUS_CACHE_KEY, $mode );
		if ( null !== $cached_status ) {
			return 'enabled' === $cached_status;
		}

		$previous_secret = WC_Stripe_API::get_secret_key();
		try {
			$webhook_id = $webhook_data['id'];
			WC_Stripe_API::set_secret_key( $secret_key );
			$webhook = $this->stripe_api::request( [], 'webhook_endpoints/' . $webhook_id, 'GET' );

			// Cache the status for 2 hours.
			$webhook_status = ! empty( $webhook->status ) && 'enabled' === $webhook->status ?
				'enabled' :
				'disabled';
			WC_Stripe_Database_Cache::set_with_mode( self::WEBHOOK_STATUS_CACHE_KEY, $webhook_status, 2 * HOUR_IN_SECONDS, $mode );

			return 'enabled' === $webhook_status;
		} catch ( Exception $e ) {
			WC_Stripe_Logger::error( 'Unable to determine webhook status', [ 'error_message' => $e->getMessage() ] );
			return false;
		} finally {
			WC_Stripe_API::set_secret_key( $previous_secret );
		}
	}

	/**
	 * Returns the API version for the webhooks.
	 *
	 * @return string The API version.
	 */
	private static function get_webhooks_api_version(): string {
		$version = WC_Stripe_API::STRIPE_API_VERSION;

		/**
		 * Agentic Commerce uses a different API version for webhooks.
		 *
		 * This method should be removed once we switch to
		 * AGENTIC_COMMERCE_API_VERSION or higher.
		 */
		if ( WC_Stripe_Feature_Flags::is_agentic_commerce_enabled() ) {
			$version = WC_Stripe_API::AGENTIC_COMMERCE_API_VERSION;
		}

		return $version;
	}
}
