import { applyFilters } from '@wordpress/hooks';

// ECE init awaits this filter, so a resolver that never settles would hang the
// button forever. Sits above WCPBC's own 6s bail on its AJAX geolocation, so it
// only fires for a truly stuck resolver.
const RESOLVER_TIMEOUT_MS = 8000;

/**
 * Threads a Promise<string> through applyFilters so resolvers can defer
 * (e.g. wait for an async currency lookup) before ECE creates Stripe Elements.
 *
 * @param {string} fallback Used if no resolver overrides.
 * @param {Object} ctx      Passed through to filter callbacks.
 * @return {Promise<string>} Lowercase ISO currency.
 */
export async function resolveExpressCheckoutCurrency( fallback, ctx ) {
	const fallbackLower = ( fallback || '' ).toLowerCase();
	let resolved = fallbackLower;
	let timer;

	try {
		const piped = applyFilters(
			'wcstripe.express-checkout.resolved-currency',
			Promise.resolve( fallbackLower ),
			ctx
		);

		const timeout = new Promise( ( resolve ) => {
			timer = setTimeout(
				() => resolve( fallbackLower ),
				RESOLVER_TIMEOUT_MS
			);
		} );

		const value = await Promise.race( [ piped, timeout ] );
		if ( typeof value === 'string' && value ) {
			resolved = value.toLowerCase();
		}
	} catch ( e ) {
		// A misbehaving resolver shouldn't break ECE init.
	} finally {
		clearTimeout( timer );
	}

	return resolved;
}
