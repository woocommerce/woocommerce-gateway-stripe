/**
 * Resolves a possibly-relative URL to an absolute same-origin URL, or null when
 * the input is empty, unparseable, or resolves to a different origin.
 *
 * @param {string} rawUrl The URL to normalize (may be relative).
 * @return {?string} An absolute same-origin URL, or null.
 */
export const normalizeReturnUrl = ( rawUrl ) => {
	if ( rawUrl ) {
		try {
			const resolved = new URL( rawUrl, window.location.origin );
			if ( resolved.origin === window.location.origin ) {
				return resolved.href;
			}
		} catch ( e ) {
			// Fall through.
		}
	}

	return null;
};
