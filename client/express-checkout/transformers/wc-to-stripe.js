import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { decodeEntities } from '@wordpress/html-entities';
import { getExpressCheckoutData } from 'wcstripe/express-checkout/utils';

/**
 * GooglePay/ApplePay expect the prices to be formatted in cents.
 * But WooCommerce has a setting to define the number of decimals for amounts.
 * Using this function to ensure the prices provided to GooglePay/ApplePay
 * are always provided accurately, regardless of the number of decimals.
 *
 * @param {number} price the price to format.
 * @param {{currency_minor_unit: {number}}} priceObject the price object returned by the Store API
 *
 * @return {number} the price amount for GooglePay/ApplePay, always expressed in cents.
 */
export const transformPrice = ( price, priceObject ) => {
	const currencyDecimals =
		getExpressCheckoutData( 'checkout' )?.currency_decimals ?? 2;

	// making sure the decimals are always correctly represented for GooglePay/ApplePay, since they don't allow us to specify the decimals.
	return price * 10 ** ( currencyDecimals - priceObject.currency_minor_unit );
};

/**
 * Transforms the data from the Store API Cart response to `displayItems` for the Stripe ECE.
 * See for the data structure:
 * - https://docs.stripe.com/js/elements_object/express_checkout_element_shippingaddresschange_event
 * - https://docs.stripe.com/js/elements_object/express_checkout_element_shippingratechange_event
 *
 * @param {Object} rawCartData Store API Cart response object.
 * @return {{pending: boolean, name: string, amount: number}} `displayItems` for Stripe.
 */
export const transformCartDataForDisplayItems = ( rawCartData ) => {
	// allowing extensions to manipulate the individual items returned by the backend.
	const cartData = applyFilters(
		'wcstripe.express-checkout.map-line-items',
		rawCartData
	);

	const displayItems = cartData.items.map( ( item ) => ( {
		amount: transformPrice(
			parseInt( item.totals?.line_subtotal || item.prices.price, 10 ),
			item.totals || item.prices
		),
		name: [
			item.name,
			item.quantity > 1 && `(x${ item.quantity })`,
			item.variation &&
				item.variation
					.map(
						( variation ) =>
							`${ variation.attribute }: ${ variation.value }`
					)
					.join( ', ' ),
		]
			.filter( Boolean )
			.map( decodeEntities )
			.join( ' ' ),
	} ) );

	const taxAmount = parseInt( cartData.totals.total_tax || '0', 10 );
	if ( taxAmount ) {
		displayItems.push( {
			amount: transformPrice( taxAmount, cartData.totals ),
			name: __( 'Tax', 'woocommerce-gateway-stripe' ),
		} );
	}

	if (
		cartData?.needs_shipping === true &&
		cartData.totals?.total_shipping
	) {
		const shippingAmount = parseInt(
			cartData.totals.total_shipping || '0',
			10
		);
		displayItems.push( {
			key: 'total_shipping',
			amount: transformPrice( shippingAmount, cartData.totals ),
			name: __( 'Shipping', 'woocommerce-gateway-stripe' ),
		} );
	}

	const discountAmount = parseInt(
		cartData.totals.total_discount || '0',
		10
	);
	if ( discountAmount ) {
		displayItems.push( {
			amount: -transformPrice( discountAmount, cartData.totals ),
			name: __( 'Discount', 'woocommerce-gateway-stripe' ),
		} );
	}

	const refundAmount = parseInt( cartData.totals.total_refund || '0', 10 );
	if ( refundAmount ) {
		displayItems.push( {
			amount: -transformPrice( refundAmount, cartData.totals ),
			name: __( 'Refund', 'woocommerce-gateway-stripe' ),
		} );
	}

	return displayItems;
};

/**
 * Transforms the `displayItems` from the Stripe ECE to the format expected by the Store API.
 *
 * @param {Array} displayItems
 * @return {Array} The transformed display items.
 */
export const transformLabeledDisplayItems = ( displayItems ) => {
	return ( displayItems ?? [] ).map( ( { label, amount } ) => ( {
		name: label,
		amount,
	} ) );
};
