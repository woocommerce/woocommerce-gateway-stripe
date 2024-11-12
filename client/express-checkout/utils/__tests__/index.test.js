/**
 * Internal dependencies
 */
import { screen, render } from '@testing-library/react';
import {
	displayExpressCheckoutNotice,
	getErrorMessageFromNotice,
	getExpressCheckoutData,
} from '..';

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
		window.wc_stripe_express_checkout_params = {
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

	describe( 'displayExpressCheckoutNotice', () => {
		afterEach( () => {
			document.getElementsByTagName( 'body' )[ 0 ].innerHTML = '';
		} );

		const additionalClasses = [ 'class-2', 'class-3' ];
		const createWrapper = () => {
			const wrapper = document.createElement( 'div' );
			wrapper.classList.add( 'woocommerce-notices-wrapper' );
			document.body.appendChild( wrapper );
		};

		test( 'with info', async () => {
			function App() {
				createWrapper();
				displayExpressCheckoutNotice(
					'Test message',
					'info',
					additionalClasses
				);
				return <div />;
			}
			render( <App /> );
			expect( screen.queryByRole( 'note' ) ).toBeInTheDocument();
		} );

		test( 'with error', () => {
			function App() {
				createWrapper();
				displayExpressCheckoutNotice(
					'Test message',
					'error',
					additionalClasses
				);
				return <div />;
			}
			render( <App /> );
			expect( screen.queryByRole( 'note' ) ).toBeInTheDocument();
		} );
	} );
} );
