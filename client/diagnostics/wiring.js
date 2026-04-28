import { getRecorder } from 'wcstripe/diagnostics/recorder';

function isActive() {
	return !! window.wcStripeDiag?.active;
}

/**
 * Diagnostics wiring helpers used by the checkout call sites.
 *
 * Every method is a no-op when window.wcStripeDiag.active is not true,
 * so production callers can sprinkle these in without conditional guards.
 *
 * Usage:
 *
 *   import { diagnostics } from 'wcstripe/diagnostics/wiring';
 *
 *   diagnostics.attach( cardElement, 'card' );
 *   diagnostics.aroundStripeCall( 'confirmPayment', () =>
 *       stripe.confirmPayment( params )
 *   );
 */
export const diagnostics = {
	/**
	 * Subscribe the recorder to a Stripe Element instance's lifecycle events.
	 * Use BEFORE the element fires its one-time `ready` (e.g. classic UPE,
	 * where attach happens at create time, before mount).
	 *
	 * @param {Object} element The Stripe Element instance.
	 * @param {string} kind    The element kind (e.g. 'card', 'payment').
	 */
	attach( element, kind ) {
		if ( ! isActive() ) return;
		getRecorder().attach( element, kind );
	},

	/**
	 * Like attach(), but for elements where the underlying Stripe Element
	 * has already fired its one-time `ready` event by the time we get here
	 * (e.g. inside React's PaymentElement onReady prop). Synthesizes the
	 * ready event so the trace stays symmetric with the classic surface.
	 *
	 * @param {Object} element The Stripe Element instance.
	 * @param {string} kind    The element kind (e.g. 'payment').
	 */
	attachAfterReady( element, kind ) {
		if ( ! isActive() ) return;
		getRecorder().attachAfterReady( element, kind );
	},

	/**
	 * Subscribe the recorder to an Express Checkout Element's events
	 * (click, confirm, cancel, shippingaddresschange, etc.).
	 *
	 * @param {Object} eceButton The Express Checkout Element instance.
	 */
	attachExpress( eceButton ) {
		if ( ! isActive() ) return;
		getRecorder().attachExpress( eceButton );
	},

	/**
	 * Record a single Express Checkout Element event. Used by the Blocks ECE
	 * surface where events arrive via React props (`onClick`, `onConfirm`, etc.)
	 * rather than `eceButton.on()`. Call from inside each prop handler.
	 *
	 * @param {string} eventName One of: click, confirm, cancel,
	 *                           shippingratechange, shippingaddresschange,
	 *                           paymentmethod.
	 * @param {Object} payload   The event payload from Stripe.
	 */
	recordExpressEvent( eventName, payload ) {
		if ( ! isActive() ) return;
		getRecorder().recordExpressEvent( eventName, payload );
	},

	/**
	 * Bracket a Stripe API call with .invoke / .resolve / .throw events.
	 *
	 *   const result = await diagnostics.aroundStripeCall(
	 *       'createPaymentMethod',
	 *       () => stripe.createPaymentMethod( params )
	 *   );
	 *
	 * Used at every Stripe SDK call site rather than mutating the Stripe
	 * instance — see recorder.js for why mutation is unsafe.
	 *
	 * @param {string}   method The Stripe method name (e.g. 'confirmPayment').
	 * @param {Function} fn     A zero-arg function that performs the Stripe call and returns its Promise.
	 * @return {Promise} The promise returned by fn(), with diagnostics events recorded when active.
	 */
	aroundStripeCall( method, fn ) {
		if ( ! isActive() ) return fn();
		return getRecorder().aroundStripeCall( method, fn );
	},

	/**
	 * Open a bracket around the Blocks onPaymentSetup handler. Returns a
	 * handle to pass to blocksPaymentSetupEnd, or null when inactive.
	 *
	 * @param {string} site Which onPaymentSetup site this is — 'payment_processor' or 'checkout_sessions'.
	 * @return {Object|null} Handle for the matching end call.
	 */
	blocksPaymentSetupStart( site ) {
		if ( ! isActive() ) return null;
		return getRecorder().recordBlocksPaymentSetupStart( site );
	},

	/**
	 * Close the bracket opened by blocksPaymentSetupStart. No-op if the
	 * handle is null (inactive at start time).
	 *
	 * @param {Object|null} handle Handle from blocksPaymentSetupStart.
	 * @param {Object}      result The onPaymentSetup result ({ type, message, ... }).
	 */
	blocksPaymentSetupEnd( handle, result ) {
		if ( ! handle || ! isActive() ) return;
		getRecorder().recordBlocksPaymentSetupEnd( handle, result );
	},
};
