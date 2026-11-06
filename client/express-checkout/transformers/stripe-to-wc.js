/**
 * Transform shipping address from Stripe's ECE event format to
 * the Store API cart shipping address format.
 *
 * Note: During shippingaddresschange events, Stripe provides semi-anonymized
 * addresses — line1, line2, and organization may be absent.
 *
 * @param {string|undefined} name            The recipient name from the ECE event.
 * @param {Object}           shippingAddress The address object from the ECE event.
 * @return {Object} Address in Store API format.
 */
export const transformStripeShippingAddressForStoreApi = (
	name,
	shippingAddress
) => {
	const nameTokens = name?.trim().split( /\s+/ ) ?? [];
	return {
		first_name: nameTokens[ 0 ] ?? '',
		last_name: nameTokens.slice( 1 ).join( ' ' ),
		company: shippingAddress?.organization ?? '',
		address_1: shippingAddress?.line1 ?? '',
		address_2: shippingAddress?.line2 ?? '',
		city: shippingAddress?.city ?? '',
		state: shippingAddress?.state ?? '',
		postcode: shippingAddress?.postal_code?.replace( /\s/g, '' ) ?? '',
		country: shippingAddress?.country ?? '',
	};
};
