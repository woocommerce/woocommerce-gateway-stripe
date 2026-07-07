/**
 * Resolves a possibly-relative URL to an absolute same-origin URL. Returns the
 * fallback when the input is empty, unparseable, or resolves to a different origin.
 *
 * @param {string}  rawUrl        The URL to normalize (may be relative).
 * @param {?string} [fallbackUrl] Fallback returned when rawUrl cannot be used.
 * @return {?string} An absolute same-origin URL, the fallback, or null.
 */
export const normalizeReturnUrl = ( rawUrl, fallbackUrl = null ) => {
	if ( rawUrl ) {
		try {
			const resolved = new URL( rawUrl, window.location.origin );
			if ( resolved.origin === window.location.origin ) {
				return resolved.href;
			}
		} catch ( e ) {
			// Fall through to the fallback below.
		}
	}

	return fallbackUrl;
};
