<?php
/**
 * EXPLORATION SPIKE — NOT LOADED, NOT PRODUCTION CODE (STRIPE-968).
 *
 * Illustrates Approach F from STRIPE-968-site-identification.md (the recommended path).
 *
 * Premise, confirmed by Stripe (2026-06):
 *   - An account has exactly ONE agentic-commerce endpoint for the synchronous
 *     customize_checkout / finalize_checkout hooks, so only the owning site is called.
 *   - Every agentic checkout fires one of those sync hooks BEFORE checkout.session.completed.
 *   - The `checkout_session` id in the sync hook equals checkout.session.completed's
 *     data.object.id, so a claim recorded at hook time can be matched at completion time.
 *
 * Idea: the owning site records ("claims") each session id it sees in a sync hook; order
 * creation (which runs on the BROADCAST checkout.session.completed event that every site on the
 * account receives) only proceeds for sessions this site claimed. Non-owning sites never saw the
 * hook, never claimed, and skip — no duplicate / wrong-site orders. No external_reference change.
 *
 * This file lives under docs/explorations/, is NOT autoloaded or registered, and is a sketch for
 * discussion only. A real implementation needs tests (claim/no-claim across two sites), a
 * considered TTL, and a decision on the failure contract before any wiring.
 *
 * @package WooCommerce_Stripe/Explorations
 */

// phpcs:ignoreFile -- exploration sketch, not held to production standards.

/**
 * Sketch of the claim helpers. Names/shapes are illustrative.
 */
class Spike_Stripe_968_Session_Claim {

	/** Cache key prefix for a claimed agentic session. Mode-scoped via WC_Stripe_Database_Cache. */
	const CLAIM_PREFIX = 'agentic_session_claim_';

	/** Generous TTL — a checkout may sit pending; must comfortably exceed hook -> completion gap. */
	const CLAIM_TTL = DAY_IN_SECONDS;

	/**
	 * Hook side: record that THIS site owns the session. Called from
	 * process_agentic_customization_hook() / process_agentic_finalize_checkout_hook(), where
	 * $event->data->checkout_session is present (see the committed sample event fixture).
	 */
	public static function claim( string $checkout_session_id ): void {
		if ( '' === $checkout_session_id ) {
			return;
		}
		WC_Stripe_Database_Cache::set( self::CLAIM_PREFIX . $checkout_session_id, 1, self::CLAIM_TTL );
	}

	/**
	 * Completion side: did this site claim the session in a prior sync hook? Called from
	 * handle_agentic_checkout_session() before create_order_from_checkout_session().
	 */
	public static function owns( string $checkout_session_id ): bool {
		if ( '' === $checkout_session_id ) {
			return false;
		}
		return null !== WC_Stripe_Database_Cache::get( self::CLAIM_PREFIX . $checkout_session_id );
	}
}

/*
 * Illustrative wiring (pseudocode, not executed):
 *
 *   // In process_agentic_customization_hook() / process_agentic_finalize_checkout_hook():
 *   Spike_Stripe_968_Session_Claim::claim( $event->data->checkout_session ?? '' );
 *
 *   // In handle_agentic_checkout_session( $notification ):
 *   $session_id = $notification->data->object->id;
 *   if ( ! Spike_Stripe_968_Session_Claim::owns( $session_id ) ) {
 *       WC_Stripe_Logger::info( 'Agentic order skipped: session not claimed by this site (likely another site on the same Stripe account).', [ 'session_id' => $session_id ] );
 *       return; // no order/data side effects
 *   }
 *   // ... proceed to create_order_from_checkout_session( $session ) ...
 *
 * Open implementation details (see doc): claim TTL, whether to also delete the claim after a
 * successful create, and the exact failure contract (logged skip recommended, mirroring
 * STRIPE-1194's account guard).
 */
