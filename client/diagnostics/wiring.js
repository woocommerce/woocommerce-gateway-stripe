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

/**
 * Like diagAttach, but for Stripe Element instances where the underlying
 * `ready` event has already fired (e.g. inside React's PaymentElement
 * onReady prop). Synthesizes the ready event so the trace stays symmetric
 * with the classic surface.
 *
 * @param {Object} element The Stripe Element instance.
 * @param {string} kind    The element kind (e.g. 'payment').
 * @param {string} surface The checkout surface (e.g. 'blocks').
 */
export function diagAttachAfterReady( element, kind, surface ) {
	if ( ! isActive() ) {
		return;
	}
	getRecorder().attachAfterReady( element, kind, surface );
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
