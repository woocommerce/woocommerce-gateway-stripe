/**
 * Replace merchant-facing markup without detaching the SDK's private mount host.
 * A normal DOM move reloads its iframe, even when Stripe reports cached readiness.
 * Return null when the caller must use Stripe's documented remount fallback.
 *
 * @param {Element} container Public wallet wrapper.
 * @param {Element} host      Private Stripe mount host.
 * @return {Element|null} New wrapper, or null when state preservation is unavailable.
 */
export const rebuildWalletContainer = ( container, host ) => {
	if (
		! container?.isConnected ||
		! host?.isConnected ||
		typeof container.moveBefore !== 'function'
	) {
		return null;
	}
	const replacement = container.cloneNode( false );
	container.before( replacement );
	try {
		replacement.moveBefore( host, null );
	} catch ( error ) {
		replacement.remove();
		return null;
	}
	container.remove();
	return replacement;
};
