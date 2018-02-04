/**
 * External dependencies
 */
import config from 'config';
import chai from 'chai';
import chaiAsPromised from 'chai-as-promised';
import test from 'selenium-webdriver/testing';

/**
 * Internal dependencies
 */
import * as t from './lib/test-helper';

chai.use( chaiAsPromised );
const assert = chai.assert;

test.describe( 'Checkout flow', function() {
	this.timeout( config.get( 'mochaTimeoutMs' ) );

	test.before( function() {
		this.timeout( config.get( 'startBrowserTimeoutMs' ) );
	} );

	test.before( t.startBrowser );

	test.describe( 'Pay with Stripe Non 3DS Required', function() {
		config.get( 'stripeCC' ).forEach( stripeSetting => {
			test.before( () => {
				t.setStripeSettings( stripeSetting );
			} );

			test.beforeEach( t.asGuestCustomer );

			test.it( 'Credit card', function() {
				t.openOnePaymentProduct().addToCart();
				t.payWithStripe( true );

				assert.eventually.ok( t.redirectedTo( '/checkout/order-received/' ) );
			} );
		} );
	} );

	test.describe( 'Pay with Stripe Non 3DS Required Non Inline', function() {
		config.get( 'stripeCCNoInline' ).forEach( stripeSetting => {
			test.before( () => {
				t.setStripeSettings( stripeSetting );
			} );

			test.beforeEach( t.asGuestCustomer );

			test.it( 'Credit card', function() {
				t.openOnePaymentProduct().addToCart();
				t.payWithStripe( false );

				assert.eventually.ok( t.redirectedTo( '/checkout/order-received/' ) );
			} );
		} );
	} );

	test.after( t.quitBrowser );
} );
