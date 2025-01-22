/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { applyFilters } from '@wordpress/hooks';
import { getExpressCheckoutData } from '../utils';

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
		'wcpay.express-checkout.map-line-items',
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
			name: __( 'Tax', 'woocommerce-payments' ),
		} );
	}

	const shippingAmount = parseInt(
		cartData.totals.total_shipping || '0',
		10
	);
	if ( shippingAmount ) {
		displayItems.push( {
			amount: transformPrice( shippingAmount, cartData.totals ),
			name: __( 'Shipping', 'woocommerce-payments' ),
		} );
	}

	const refundAmount = parseInt( cartData.totals.total_refund || '0', 10 );
	if ( refundAmount ) {
		displayItems.push( {
			amount: -transformPrice( refundAmount, cartData.totals ),
			name: __( 'Refund', 'woocommerce-payments' ),
		} );
	}

	return displayItems;
};

/**
 * Transforms the data from the Store API Cart response to `shippingRates` for the Stripe ECE.
 *
 * @param {Object} cartData Store API Cart response object.
 * @return {{id: string, label: string, amount: number, deliveryEstimate: string}} `shippingRates` for Stripe.
 */
export const transformCartDataForShippingRates = ( cartData ) =>
	cartData.shipping_rates?.[ 0 ]?.shipping_rates
		.sort( ( rateA, rateB ) => {
			if ( rateA.selected === rateB.selected ) {
				return 0; // Keep relative order if both have the same value for 'selected'
			}

			return rateA.selected ? -1 : 1; // Objects with 'selected: true' come first
		} )
		.map( ( rate ) => ( {
			id: rate.rate_id,
			displayName: decodeEntities( rate.name ),
			amount: transformPrice( parseInt( rate.price, 10 ), rate ),
			deliveryEstimate: [
				rate.meta_data.find(
					( metadata ) => metadata.key === 'pickup_address'
				)?.value,
				rate.meta_data.find(
					( metadata ) => metadata.key === 'pickup_details'
				)?.value,
			]
				.filter( Boolean )
				.map( decodeEntities )
				.join( ' - ' ),
		} ) );
