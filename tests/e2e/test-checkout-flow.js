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

	test.describe( 'One-time payment', function() {

	} );

	test.after( t.quitBrowser );
} );
