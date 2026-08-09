import { applyFilters } from '@wordpress/hooks';

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
	const piped = applyFilters(
		'wc-stripe.express-checkout.resolved-currency',
		Promise.resolve( fallbackLower ),
		ctx
	);

	let resolved = fallbackLower;
	try {
		const value = await piped;
		if ( typeof value === 'string' && value ) {
			resolved = value.toLowerCase();
		}
	} catch ( e ) {
		// A misbehaving resolver shouldn't break ECE init.
	}

	return resolved;
}
