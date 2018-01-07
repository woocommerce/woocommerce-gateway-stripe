/**
 * External dependencies
 */
import { GuestCustomerFlow as Base, SingleProductPage } from 'wc-e2e-page-objects';

/**
 * Internal dependencies
 */
import CartPage from './cart-page.js';
import CheckoutPage from './checkout-page.js';

export default class GuestCustomerFlow extends Base {
	constructor( driver, args = {} ) {
		super( driver, args );
	}

	openCart() {
		return this.open( {
			object: CartPage,
			path: '/cart'
		} );
	}

	openCheckout() {
		return this.open( {
			object: CheckoutPage,
			path: '/checkout'
		} );
	}

	/**
	 * TODO: Add this to wc-e2e-page-objects.
	 *
	 * @param {String} path - Single product path.
	 */
	fromProductPathAddToCart( path ) {
		const product = this.open( {
			object: SingleProductPage,
			path: path
		} );

		product.addToCart();
	}
}
