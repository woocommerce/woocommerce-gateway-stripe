<?php

/**
 * Stateful Stripe transport for upgrade tests. Keeping the real API client catches
 * writes from constructors and settings reads as well as explicit migrations.
 */
class WC_Stripe_Upgrade_HTTP_Fake {
	public array $requests        = [];
	public array $unexpected      = [];
	private array $webhooks       = [];
	private array $configurations = [];

	public function __construct( array $fixture ) {
		$events = $fixture['webhook_events'];
		foreach ( [ 'live', 'test' ] as $mode ) {
			$this->webhooks[ $mode ] = [
				'we_' . $mode . '_old' => [
					'id'             => 'we_' . $mode . '_old',
					'url'            => WC_Stripe_Helper::get_webhook_url(),
					'status'         => 'enabled',
					'api_version'    => $fixture['webhook_api_version'],
					'enabled_events' => $events,
				],
			];
			$configuration           = [
				'id'       => 'pmc_' . $mode . '_fixture',
				'active'   => true,
				'livemode' => 'live' === $mode,
				'parent'   => 'live' === $mode ? 'pmc_1LEKjAGX8lmJQndTk2ziRchV' : 'pmc_1LEKjBGX8lmJQndTBOzjqxSa',
			];
			foreach ( $fixture['pmc_preferences'] as $id => $preference ) {
				$configuration[ $id ] = [ 'display_preference' => [ 'value' => $preference ] ];
			}
			$this->configurations[ $mode ] = $configuration;
		}
	}

	public function intercept( $preempt, $args, $url ) {
		$method = strtoupper( $args['method'] );
		$path   = wp_parse_url( $url, PHP_URL_PATH );
		$body   = $args['body'] ?? [];
		if ( is_string( $body ) ) {
			parse_str( $body, $body );
		}
		$authorization = $args['headers']['Authorization'] ?? '';
		$mode          = null;
		foreach ( [ 'live', 'test' ] as $candidate ) {
			if ( 'Basic ' . base64_encode( 'sk_' . $candidate . '_upgrade_fixture:' ) === $authorization ) { // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Verify the API client's HTTP Basic authentication.
				$mode = $candidate;
			}
		}
		$request          = [
			'method' => $method,
			'path'   => $path,
			'mode'   => $mode,
			'body'   => $body,
		];
		$this->requests[] = $request;
		$response         = null;
		if ( 'api.stripe.com' === wp_parse_url( $url, PHP_URL_HOST ) && null !== $mode ) {
			if ( 'GET' === $method && '/v1/account' === $path ) {
				$response = [
					'id'               => 'acct_upgrade_fixture',
					'country'          => 'US',
					'created'          => 1700000000,
					'default_currency' => 'usd',
					'charges_enabled'  => true,
					'capabilities'     => [
						'card_payments'   => 'active',
						'klarna_payments' => 'active',
						'link_payments'   => 'active',
					],
				];
			} elseif ( 'GET' === $method && '/v1/payment_method_configurations' === $path ) {
				$response = [ 'data' => [ $this->configurations[ $mode ] ] ];
			} elseif ( 'POST' === $method && '/v1/payment_method_configurations/pmc_' . $mode . '_fixture' === $path ) {
				foreach ( $body as $id => $preference ) {
					$this->configurations[ $mode ][ $id ] = [ 'display_preference' => [ 'value' => $preference['display_preference']['preference'] ] ];
				}
				$response = $this->configurations[ $mode ];
			} elseif ( 'GET' === $method && '/v1/webhook_endpoints' === $path ) {
				$response = [ 'data' => array_values( $this->webhooks[ $mode ] ) ];
			} elseif ( 'POST' === $method && '/v1/webhook_endpoints' === $path ) {
				$id                             = 'we_' . $mode . '_new';
				$response                       = array_merge(
					$body,
					[
						'id'     => $id,
						'status' => 'enabled',
						'secret' => 'whsec_' . $mode . '_new_fixture',
					]
				);
				$this->webhooks[ $mode ][ $id ] = $response;
			} elseif ( 0 === strpos( $path, '/v1/webhook_endpoints/' ) && isset( $this->webhooks[ $mode ][ basename( $path ) ] ) ) {
				$id = basename( $path );
				if ( 'GET' === $method ) {
					$response = $this->webhooks[ $mode ][ $id ];
				} elseif ( 'DELETE' === $method ) {
					unset( $this->webhooks[ $mode ][ $id ] );
					$response = [
						'id'      => $id,
						'deleted' => true,
					];
				}
			}
		}
		$unexpected = null === $response;
		if ( $unexpected ) {
			// Record before returning: production code may catch HTTP failures, but
			// that must not make an unexpected request invisible to the test.
			$this->unexpected[] = $request;
			$response           = [
				'error' => [
					'message' => 'Unexpected upgrade HTTP request',
					'type'    => 'invalid_request_error',
				],
			];
		}
		return [
			'headers'  => [],
			'response' => [ 'code' => $unexpected ? 400 : 200 ],
			'body'     => wp_json_encode( $response ),
		];
	}

	public function writes(): array {
		return array_values(
			array_filter(
				$this->requests,
				static function ( $request ) {
					return ! in_array( $request['method'], [ 'GET', 'HEAD' ], true );
				}
			)
		);
	}
}
