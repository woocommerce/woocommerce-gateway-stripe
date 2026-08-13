<?php
/**
 * Localizes window.wcStripeDiag on pages that load the Stripe checkout
 * bundles, so client/diagnostics/recorder.js can boot.
 */

defined( 'ABSPATH' ) || exit;

class WC_Stripe_Diagnostics_Frontend_Loader {

	/**
	 * Script handles the recorder is bundled into. The loader attaches an
	 * inline `wcStripeDiag` global to whichever of these is enqueued on
	 * the current page.
	 */
	protected const SCRIPT_HANDLES = [
		'wc-stripe-upe-classic',
		'wc-stripe-blocks-integration',
		// Express checkout is the only Stripe bundle on product pages, so
		// without it as a host the recorder never activates there.
		'wc_stripe_express_checkout',
	];

	/**
	 * Key under which the diag session id is stored in the WC session.
	 * Tying the id to the WC session keeps it stable across pageviews
	 * (including 3DS redirects) for the same shopper.
	 */
	protected const SESSION_KEY = 'wc_stripe_diag_session_id';

	/**
	 * Cached inline-config string for this request.
	 *
	 * @var string|null
	 */
	private $cached = null;

	public function init(): void {
		add_action( 'wp_footer', [ $this, 'maybe_localize' ], 11 );
	}

	/**
	 * Inline-attach the recorder config to whichever recorder-host
	 * script is on the page. Hooked at wp_footer priority 11 so the
	 * gateway's own payment_scripts() callback (priority 10) has
	 * already enqueued/registered its handle.
	 */
	public function maybe_localize(): void {
		if ( ! $this->should_localize() ) {
			return;
		}
		$inline = $this->build_inline_config();
		if ( null === $inline ) {
			return;
		}
		foreach ( self::SCRIPT_HANDLES as $handle ) {
			if ( wp_script_is( $handle, 'enqueued' ) || wp_script_is( $handle, 'registered' ) ) {
				wp_add_inline_script( $handle, $inline, 'before' );
			}
		}
	}

	/**
	 * Whether the loader should emit `wcStripeDiag` for this request.
	 *
	 * Skipped when the toggle is off, or when the request has no
	 * established WC session — writing to the WC session in that state
	 * would set a session cookie and break full-page caching for
	 * first-time visitors. Logged-in users are covered by the same
	 * check because WC_Session_Handler::has_session() returns true for
	 * them.
	 */
	public function should_localize(): bool {
		if ( ! WC_REST_Stripe_Diagnostics_Controller::is_enabled() ) {
			return false;
		}
		$session = WC()->session;
		return $session instanceof WC_Session_Handler && $session->has_session();
	}

	/**
	 * Build the inline `window.wcStripeDiag = …;` snippet, cached so a
	 * page that has multiple recorder-host handles enqueued sees the
	 * same sessionId/nonce.
	 */
	private function build_inline_config(): ?string {
		if ( null !== $this->cached ) {
			return $this->cached;
		}

		$config = [
			'active'    => true,
			'sessionId' => $this->get_or_create_session_id(),
			'endpoint'  => rest_url( 'wc/v3/wc_stripe/diagnostics/events' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
		];

		$encoded = wp_json_encode( $config );
		if ( false === $encoded ) {
			return null;
		}

		$this->cached = 'window.wcStripeDiag = ' . $encoded . ';';
		return $this->cached;
	}

	/**
	 * Read the diag session id from the WC session, generating and
	 * persisting one if missing. Stable across pageviews while the WC
	 * session lives.
	 */
	public function get_or_create_session_id(): string {
		$session = WC()->session;
		$id      = $session->get( self::SESSION_KEY );
		if ( ! is_string( $id ) || ! self::looks_like_uuid( $id ) ) {
			$id = wp_generate_uuid4();
			$session->set( self::SESSION_KEY, $id );
		}
		return $id;
	}

	private static function looks_like_uuid( string $candidate ): bool {
		return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $candidate );
	}
}
