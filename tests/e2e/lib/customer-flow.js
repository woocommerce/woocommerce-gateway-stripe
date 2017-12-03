/**
 * External dependencies
 */
import { CustomerFlow as Base } from 'wc-e2e-page-objects';

/**
 * Internal dependencies
 */
import CheckoutPage from './checkout-page.js';

export default class CustomerFlow extends Base {
	constructor( driver, args = {} ) {
		super( driver, args );
	}

	openCheckout() {
		return this.open( {
			object: CheckoutPage,
			path: '/checkout'
		} );
	}
}
