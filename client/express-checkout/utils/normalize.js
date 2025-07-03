import { select } from '@wordpress/data';
import { applyFilters } from '@wordpress/hooks';
import { getExpressCheckoutData } from 'wcstripe/express-checkout/utils';

/**
 * Normalizes incoming cart total items for use as a displayItems with the Stripe api.
 *
 * @param {Array} displayItems Items to normalize.
 *
 * @return {Array} An array of PaymentItems
 */
export const normalizeLineItems = ( displayItems ) => {
	return displayItems.map( ( displayItem ) => {
		let amount = displayItem?.amount ?? displayItem?.value;
		if ( displayItem.key === 'total_discount' ) {
			amount = -amount;
		}

		return {
			name: displayItem.label,
			amount,
		};
	} );
};

/**
 * Normalize order data from Stripe's object to the expected format for WC (when using the Blocks API).
 *
 * @param {Object} params
 * @param {Object} params.event Stripe's event object.
 * @param {string} params.paymentMethodId Payment method ID from Stripe, if using manual payment method flow.
 * @param {string} params.confirmationTokenId Confirmation token ID from Stripe, if using confirmation token flow.*
 *
 * @return {Object} Order object in the format WooCommerce expects.
 */
export const normalizeOrderData = ( {
	event,
	paymentMethodId = '',
	confirmationTokenId = '',
} ) => {
	return {
		billing_address: getBillingAddressData( event ),
		shipping_address: getShippingAddressData( event ),
		payment_method: 'stripe',
		payment_data: buildBlocksAPIPaymentData( {
			expressPaymentType: event?.expressPaymentType,
			paymentMethodId,
			confirmationTokenId,
		} ),
		extensions: applyFilters(
			'wcstripe.express-checkout.cart-place-order-extension-data',
			getExtensionDataFromStore()
		),
		additional_fields: getAdditionalFieldsData(),
	};
};

/**
 * Get billing address data from the event, and format it for the Store API.
 *
 * @param {Object} event Stripe's event object.
 *
 * @return {Object} The billing address data.
 */
const getBillingAddressData = ( event ) => {
	const name = event?.billingDetails?.name;
	const email = event?.billingDetails?.email ?? '';
	const billing = event?.billingDetails?.address ?? {};

	const data = {
		first_name: approximateFirstName( name ),
		last_name: approximateLastName( name, '-' ),
		company: billing?.organization ?? '',
		email: email ?? event?.payerEmail ?? '',
		phone: getPhone( event ),
		country: billing?.country ?? '',
		address_1: billing?.line1 ?? '',
		address_2: billing?.line2 ?? '',
		city: billing?.city ?? '',
		state: billing?.state ?? '',
		postcode: billing?.postal_code ?? '',
	};

	return {
		...getCustomBillingAddressData( data ),
		...data,
	};
};

/**
 * Get shipping address data from the event, and format it for the Store API.
 *
 * @param {Object} event Stripe's event object.
 *
 * @return {Object} The shipping address data.
 */
const getShippingAddressData = ( event ) => {
	const shipping = event?.shippingAddress ?? {};
	const name = shipping?.name;

	const data = {
		first_name: approximateFirstName( name ),
		last_name: approximateLastName( name ),
		company: shipping?.organization ?? '',
		phone: getPhone( event ),
		country: shipping?.address?.country ?? '',
		address_1: shipping?.address?.line1 ?? '',
		address_2: shipping?.address?.line2 ?? '',
		city: shipping?.address?.city ?? '',
		state: shipping?.address?.state ?? '',
		postcode: shipping?.address?.postal_code ?? '',
		method: [ event?.shippingRate?.id ?? null ],
	};

	return {
		...getCustomShippingAddressData( data ),
		...data,
	};
};

/**
 * Get the approximate first name from the full name.
 *
 * @param {string|undefined} name The full name.
 * @param {string} defaultValue The default string to return if the name
 * is undefined or empty.
 *
 * @return {string} The approximate first name.
 */
const approximateFirstName = ( name, defaultValue = '' ) => {
	return name?.split( ' ' )?.slice( 0, 1 )?.join( ' ' ) ?? defaultValue;
};

/**
 * Get the approximate last name from the full name.
 *
 * @param {string|undefined} name The full name.
 * @param {string} defaultValue The default string to return if the name
 * is undefined or empty.
 *
 * @return {string} The approximate last name.
 */
const approximateLastName = ( name, defaultValue = '' ) => {
	return name?.split( ' ' )?.slice( 1 )?.join( ' ' ) ?? defaultValue;
};

/**
 * Get custom billing address field data.
 *
 * @param {Object} data The standard billing address data.
 *
 * @return {Object} The custom billing address data.
 */
const getCustomBillingAddressData = ( data ) => {
	// We need to specifically pass empty fields when not on the block checkout page,
	// to avoid sending "hidden" and possibly stale data from previous transactions,
	// e.g. shopper is on the product page (hence, no checkout form fields are displayed),
	// but session still holds data from a previous checkout.
	if ( ! isBlockCheckoutPage() ) {
		return emptyCustomFieldObject( [ 'address' ] );
	}

	const customerData = getCustomerDataFromStore();

	if ( ! customerData || ! customerData.billingAddress ) {
		return {};
	}

	const customBillingAddressData = {};

	// Get properties present in customerData.billingAddress but not in
	// the standard, expected billing address data.
	const customBillingAddressKeys = Object.keys(
		customerData.billingAddress
	).filter( ( key ) => ! Object.prototype.hasOwnProperty.call( data, key ) );

	customBillingAddressKeys.forEach( ( key ) => {
		customBillingAddressData[ key ] = customerData.billingAddress[ key ];
	} );

	return customBillingAddressData;
};

/**
 * Get custom shipping address field data.
 *
 * @param {Object} data The standard shipping address data.
 *
 * @return {Object} The custom shipping address data.
 */
const getCustomShippingAddressData = ( data ) => {
	// We need to specifically pass empty fields when not on the block checkout page,
	// to avoid sending "hidden" and possibly stale data from previous transactions,
	// e.g. shopper is on the product page (hence, no checkout form fields are displayed),
	// but session still holds data from a previous checkout.
	if ( ! isBlockCheckoutPage() ) {
		return emptyCustomFieldObject( [ 'address' ] );
	}

	const customerData = getCustomerDataFromStore();

	if ( ! customerData?.shippingAddress ) {
		return {};
	}

	const customShippingAddressData = {};

	// Get properties present in customerData.shippingAddress but not in
	// the standard, expected shipping address data.
	const customShippingAddressKeys = Object.keys(
		customerData.shippingAddress
	).filter( ( key ) => ! Object.prototype.hasOwnProperty.call( data, key ) );

	customShippingAddressKeys.forEach( ( key ) => {
		customShippingAddressData[ key ] = customerData.shippingAddress[ key ];
	} );

	return customShippingAddressData;
};

/**
 * Get customer data from the cart store.
 *
 * @return {Object} The customer data.
 */
const getCustomerDataFromStore = () => {
	const cartStore = window.wc?.wcBlocksData?.cartStore;
	if ( ! cartStore ) {
		return {};
	}

	const store = select( cartStore );
	if ( ! store ) {
		return {};
	}

	return store.getCustomerData() || {};
};

/**
 * Get the phone number from the event.
 *
 * @param {Object} event Stripe's event object.
 *
 * @return {string} The phone number, or an empty string.
 */
const getPhone = ( event ) => {
	return (
		event?.billingDetails?.phone?.replace( /[() -]/g, '' ) ??
		event?.payerPhone?.replace( /[() -]/g, '' ) ??
		''
	);
};

/**
 * Get additional fields data.
 *
 * @return {Object} The additional fields data.
 */
const getAdditionalFieldsData = () => {
	// We need to specifically pass empty fields when not on the block checkout page,
	// to avoid sending "hidden" and possibly stale data from previous transactions,
	// e.g. shopper is on the product page (hence, no checkout form fields are displayed),
	// but session still holds data from a previous checkout.
	if ( ! isBlockCheckoutPage() ) {
		return emptyCustomFieldObject( [ 'contact', 'order' ] );
	}

	return getAdditionalFieldsDataFromStore();
};

/**
 * Get additional fields data from the checkout store.
 *
 * @return {Object} The additional fields data.
 */
const getAdditionalFieldsDataFromStore = () => {
	const checkoutStore = window.wc?.wcBlocksData?.checkoutStore;
	if ( ! checkoutStore ) {
		return {};
	}

	const store = select( checkoutStore );
	if ( ! store ) {
		return {};
	}

	return store.getAdditionalFields() || {};
};

/**
 * Get extension data from the checkout store.
 *
 * @return {Object} The extension data.
 */
const getExtensionDataFromStore = () => {
	const checkoutStore = window.wc?.wcBlocksData?.checkoutStore;
	if ( ! checkoutStore ) {
		return {};
	}

	const store = select( checkoutStore );
	if ( ! store ) {
		return {};
	}

	return store?.getExtensionData() || {};
};

/**
 * Build the custom fields object with empty values.
 *
 * @param {Array} locations A list of locations we are interested in,
 * e.g. [ 'address', 'contact', 'order' ].
 *
 * @return {Object} The custom fields object with empty values.
 */
const emptyCustomFieldObject = ( locations ) => {
	const customFields = getExpressCheckoutData( 'custom_checkout_fields' );
	if ( ! customFields ) {
		return {};
	}

	const customFieldObject = Object.entries( customFields ).reduce(
		( acc, [ field, config ] ) => {
			if ( locations.includes( config.location ) ) {
				acc[ field ] = '';
			}
			return acc;
		},
		{}
	);

	return customFieldObject;
};

/**
 * Check if the current page is a block checkout page.
 *
 * @return {boolean} True if the current page is a block checkout page, false otherwise.
 */
const isBlockCheckoutPage = () => {
	return (
		getExpressCheckoutData( 'has_block' ) &&
		getExpressCheckoutData( 'is_checkout_page' )
	);
};

/**
 * Normalize shipping address information from Stripe's address object to
 * the cart shipping address object shape.
 *
 * @param {Object} shippingAddress Stripe's shipping address item
 *
 * @return {Object} The shipping address in the shape expected by the cart.
 */
export const normalizeShippingAddress = ( shippingAddress ) => {
	return {
		first_name:
			shippingAddress?.recipient
				?.split( ' ' )
				?.slice( 0, 1 )
				?.join( ' ' ) ?? '',
		last_name:
			shippingAddress?.recipient?.split( ' ' )?.slice( 1 )?.join( ' ' ) ??
			'',
		company: '',
		address_1: shippingAddress?.addressLine?.[ 0 ] ?? '',
		address_2: shippingAddress?.addressLine?.[ 1 ] ?? '',
		city: shippingAddress?.city ?? '',
		state: shippingAddress?.state ?? '',
		country: shippingAddress?.country ?? '',
		postcode: shippingAddress?.postal_code ?? '',
	};
};

/**
 * Builds the payment data for the Blocks API.
 *
 * @param {Object} params
 * @param {string} params.expressPaymentType The express payment type.
 * @param {string} params.paymentMethodId The payment method ID.
 * @param {string} params.confirmationTokenId The confirmation token ID.
 *
 * @return {Array} The payment data.
 */
const buildBlocksAPIPaymentData = ( {
	expressPaymentType,
	paymentMethodId = '',
	confirmationTokenId = '',
} ) => {
	return [
		{
			key: 'payment_method',
			value: 'stripe',
		},
		{
			key: 'wc-stripe-payment-method',
			value: paymentMethodId,
		},
		{
			key: 'wc-stripe-confirmation-token',
			value: confirmationTokenId,
		},
		{
			key: 'express_payment_type',
			value: expressPaymentType,
		},
		{
			key: 'wc-stripe-is-deferred-intent',
			value: true,
		},
	];
};
