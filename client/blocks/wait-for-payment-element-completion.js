/**
 * Waits for the Stripe Payment Element to report that it is complete.
 *
 * A re-mount (e.g. a cart update flipping the amount `mode`) briefly resets
 * the element's completion state; this gives an in-flight (re)mount a short
 * window to settle before a submission is rejected. Completeness is read from
 * a ref so an already in-flight `onPaymentSetup` callback sees live updates
 * rather than its stale closure value. See #5490.
 *
 * @param {{current: boolean}} completeRef          Ref whose `.current` reflects Payment Element completeness.
 * @param {Object}             [options]            Optional tuning.
 * @param {number}             [options.timeoutMs]  Max time to wait, in ms (default 1000).
 * @param {number}             [options.intervalMs] Poll interval, in ms (default 50).
 * @return {Promise<boolean>} Resolves true if the element became complete within the timeout, otherwise false.
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
