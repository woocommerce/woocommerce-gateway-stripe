/**
 * Internal dependencies
 */
import { getErrorMessageFromNotice, getExpressCheckoutData } from '..';

describe( 'Express checkout utils', () => {
	test( 'getExpressCheckoutData returns null for missing option', () => {
		expect(
			getExpressCheckoutData(
				// Force wrong usage, just in case this is called from JS with incorrect params.
				'does-not-exist'
			)
		).toBeNull();
	} );

	test( 'getExpressCheckoutData returns correct value for present option', () => {
		// We don't care that the implementation is partial for the purposes of the test, so
		// the type assertion is fine.
		global.wc_stripe_express_checkout_params = {
			ajax_url: 'test',
		};

		expect( getExpressCheckoutData( 'ajax_url' ) ).toBe( 'test' );
	} );

	test( 'getErrorMessageFromNotice strips formatting', () => {
		const notice = '<p><b>Error:</b> Payment failed.</p>';
		expect( getErrorMessageFromNotice( notice ) ).toBe(
			'Error: Payment failed.'
		);
	} );

	test( 'getErrorMessageFromNotice strips scripts', () => {
		const notice =
			'<p><b>Error:</b> Payment failed.<script>alert("hello")</script></p>';
		expect( getErrorMessageFromNotice( notice ) ).toBe(
			'Error: Payment failed.alert("hello")'
		);
	} );
} );
