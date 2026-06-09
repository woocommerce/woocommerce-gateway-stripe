/**
 * Waits for the Stripe Payment Element to report that it is complete.
 *
 * On Blocks checkout the Payment Element can briefly re-mount — for example
 * when the cart amount crosses the setup/payment `mode` threshold or the
 * Optimized Checkout payment-method configuration changes — which resets its
 * completion state. A submission landing in that window would otherwise fail
 * immediately with "Your payment information is incomplete". This mirrors the
 * classic-checkout re-mount race fix by giving an in-flight (re)mount a short
 * window to settle before the submission is rejected.
 *
 * The completion state is read from a ref (not React state) so an already
 * in-flight `onPaymentSetup` callback observes live updates instead of the
 * value captured when its closure was created.
 *
 * See https://github.com/woocommerce/woocommerce-gateway-stripe/issues/5490.
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
