import { getRecorder } from 'wcstripe/diagnostics/recorder';

function isActive() {
	return !! window.wcStripeDiag?.active;
}

export function diagAttachExpress( eceButton ) {
	if ( ! isActive() ) {
		return;
	}
	getRecorder().attachExpress( eceButton );
}

export function diagAttach( element, kind, surface ) {
	if ( ! isActive() ) {
		return;
	}
	getRecorder().attach( element, kind, surface );
}

export function diagBlocksPaymentSetupStart( site ) {
	if ( ! isActive() ) {
		return null;
	}
	return getRecorder().recordBlocksPaymentSetupStart( site );
}

export function diagBlocksPaymentSetupEnd( handle, result ) {
	if ( ! handle || ! isActive() ) {
		return;
	}
	getRecorder().recordBlocksPaymentSetupEnd( handle, result );
}

/**
 * Bracket a Stripe API call with .invoke / .resolve / .throw events.
 *
 * Use this for Stripe methods we cannot wrap on the singleton itself
 * (currently `createPaymentMethod` — wrapping it breaks <Elements>).
 *
 *   const result = await diagAroundStripeCall(
 *       'createPaymentMethod',
 *       () => stripe.createPaymentMethod( params )
 *   );
 *
 * When diagnostics is inactive this passes the call through transparently.
 *
 * @param {string}   method The Stripe method name (e.g. 'createPaymentMethod').
 * @param {Function} fn     A zero-arg function that performs the Stripe call and returns its Promise.
 * @return {Promise} The promise returned by fn(), with diagnostics events recorded when active.
 */
export function diagAroundStripeCall( method, fn ) {
	if ( ! isActive() ) {
		return fn();
	}
	return getRecorder().aroundStripeCall( method, fn );
}
