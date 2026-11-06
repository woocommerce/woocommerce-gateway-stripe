/**
 * Waits for the Stripe Payment Element to report completion.
 *
 * A re-mount (e.g. a cart update flipping the amount `mode`) briefly resets
 * completion, so this gives an in-flight (re)mount a short window to settle
 * before a submission is rejected. Read from a ref so an in-flight callback
 * sees live updates, not a stale closure value.
 *
 * @param {{current: boolean}} completeRef          Ref whose `.current` reflects element completeness.
 * @param {Object}             [options]            Optional tuning.
 * @param {number}             [options.timeoutMs]  Max wait in ms (default 1000).
 * @param {number}             [options.intervalMs] Poll interval in ms (default 50).
 * @return {Promise<boolean>} True if complete within the timeout, else false.
 */
export const waitForPaymentElementCompletion = (
	completeRef,
	{ timeoutMs = 1000, intervalMs = 50 } = {}
) =>
	new Promise( ( resolve ) => {
		if ( completeRef?.current ) {
			resolve( true );
			return;
		}

		const startedAt = Date.now();
		const intervalId = setInterval( () => {
			if ( completeRef?.current ) {
				clearInterval( intervalId );
				resolve( true );
			} else if ( Date.now() - startedAt >= timeoutMs ) {
				clearInterval( intervalId );
				resolve( false );
			}
		}, intervalMs );
	} );
