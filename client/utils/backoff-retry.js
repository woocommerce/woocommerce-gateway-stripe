/**
 * Retry an action with exponential back-off for as long as a time window, counted from the first
 * scheduled retry, allows.
 *
 * Each `schedule()` waits twice as long as the one before. The last wait is shortened so it ends exactly
 * when the window does, and `schedule()` refuses once the window has passed, letting the caller give up.
 *
 * @param {Function} action              Called when a scheduled retry is due.
 * @param {Object}   [options]           Options.
 * @param {number}   [options.baseDelay] First wait, in milliseconds.
 * @return {{schedule: function(number): boolean, cancel: function(): void, reset: function(): void}} Controls.
 */
export function createBackoffRetry( action, { baseDelay = 250 } = {} ) {
	// Browsers run a setTimeout delay above this immediately, which would turn a long back-off into a
	// tight loop.
	const MAX_DELAY = 2147483647;

	let attempts = 0;
	let startedAt = 0;
	let timer = null;

	const cancel = () => {
		clearTimeout( timer );
		timer = null;
	};

	return {
		/**
		 * Schedule the next retry.
		 *
		 * @param {number} windowMs How long retrying may go on, in milliseconds, from the first retry.
		 * @return {boolean} False once the window has passed, or when it isn't a positive finite number.
		 */
		schedule( windowMs ) {
			if ( ! Number.isFinite( windowMs ) || windowMs <= 0 ) {
				return false;
			}

			const now = Date.now();
			if ( attempts === 0 ) {
				startedAt = now;
			}

			const remaining = startedAt + windowMs - now;
			if ( remaining <= 0 ) {
				return false;
			}

			const delay = Math.min(
				baseDelay * 2 ** attempts,
				remaining,
				MAX_DELAY
			);
			attempts += 1;
			cancel();
			timer = setTimeout( () => {
				timer = null;
				action();
			}, delay );

			return true;
		},

		/**
		 * Drop the pending retry, keeping the back-off where it is.
		 */
		cancel,

		/**
		 * Drop the pending retry and start the back-off over.
		 */
		reset() {
			cancel();
			attempts = 0;
		},
	};
}
