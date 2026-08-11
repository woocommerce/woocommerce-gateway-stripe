/**
 * Internal dependencies
 */
import { screen, render } from '@testing-library/react';
import {
	displayExpressCheckoutNotice,
	getErrorMessageFromNotice,
	getExpressCheckoutButtonStyleSettings,
	getExpressCheckoutData,
	getPaymentMethodTypesForExpressMethod,
} from '..';
import {
	EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY,
	EXPRESS_PAYMENT_METHOD_SETTING_LINK,
	PAYMENT_METHOD_AMAZON_PAY,
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_LINK,
} from 'wcstripe/stripe-utils/constants';
import { isAmazonPayEnabled, isLinkEnabled } from 'wcstripe/stripe-utils';

jest.mock( 'wcstripe/stripe-utils', () => ( {
	isAmazonPayEnabled: jest.fn(),
	isLinkEnabled: jest.fn(),
} ) );

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

	describe( 'getPaymentMethodTypesForExpressMethod', () => {
		test( 'default', () => {
			const paymentMethodTypes =
				getPaymentMethodTypesForExpressMethod( PAYMENT_METHOD_CARD );
			expect( paymentMethodTypes ).toEqual( [ PAYMENT_METHOD_CARD ] );
		} );
		test( 'Link, disabled', () => {
			const paymentMethodTypes =
				getPaymentMethodTypesForExpressMethod( PAYMENT_METHOD_LINK );
			expect( paymentMethodTypes ).toEqual( [ PAYMENT_METHOD_CARD ] );
		} );
		test( 'Link, enabled', () => {
			isLinkEnabled.mockReturnValue( {
				card: {},
				link: {},
			} );
			const paymentMethodTypes =
				getPaymentMethodTypesForExpressMethod( PAYMENT_METHOD_LINK );
			expect( paymentMethodTypes ).toEqual( [
				PAYMENT_METHOD_CARD,
				PAYMENT_METHOD_LINK,
			] );
		} );
		test( 'Amazon Pay, disabled', () => {
			const paymentMethodTypes = getPaymentMethodTypesForExpressMethod(
				EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY
			);
			expect( paymentMethodTypes ).toEqual( [ PAYMENT_METHOD_CARD ] );
		} );
		test( 'Amazon Pay, enabled', () => {
			isAmazonPayEnabled.mockReturnValue( {
				amazonPay: {},
			} );
			const paymentMethodTypes = getPaymentMethodTypesForExpressMethod(
				EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY
			);
			expect( paymentMethodTypes ).toEqual( [
				PAYMENT_METHOD_AMAZON_PAY,
			] );
		} );
	} );

	describe( 'getExpressCheckoutButtonStyleSettings', () => {
		afterEach( () => {
			delete window.wc_stripe_express_checkout_params;
		} );

		test( 'uses the shared button height for Apple/Google Pay', () => {
			window.wc_stripe_express_checkout_params = {
				button: { height: '40' },
				link_button_height: '56',
				amazon_pay_button_height: '48',
			};

			// Apple/Google Pay must use the shared height, not Link's or Amazon Pay's.
			expect(
				getExpressCheckoutButtonStyleSettings(
					EXPRESS_PAYMENT_METHOD_SETTING_APPLE_PAY
				).buttonHeight
			).toBe( 40 );
		} );

		test.each( [
			[ '40', 40 ],
			[ '48', 48 ],
			[ '56', 55 ],
		] )(
			'uses the Link button height (%s px) for Link, clamped to 40-55',
			( linkHeight, expected ) => {
				window.wc_stripe_express_checkout_params = {
					button: { height: '48' },
					link_button_height: linkHeight,
				};

				expect(
					getExpressCheckoutButtonStyleSettings(
						EXPRESS_PAYMENT_METHOD_SETTING_LINK
					).buttonHeight
				).toBe( expected );
			}
		);

		test( 'falls back to 48px for Link when no link height is set', () => {
			window.wc_stripe_express_checkout_params = {
				button: { height: '40' },
			};

			expect(
				getExpressCheckoutButtonStyleSettings(
					EXPRESS_PAYMENT_METHOD_SETTING_LINK
				).buttonHeight
			).toBe( 48 );
		} );

		test.each( [
			[ '40', 40 ],
			[ '48', 48 ],
			[ '56', 55 ],
		] )(
			'uses the Amazon Pay button height (%s px) for Amazon Pay, clamped to 40-55',
			( amazonHeight, expected ) => {
				window.wc_stripe_express_checkout_params = {
					button: { height: '48' },
					amazon_pay_button_height: amazonHeight,
				};

				expect(
					getExpressCheckoutButtonStyleSettings(
						EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY
					).buttonHeight
				).toBe( expected );
			}
		);

		test( 'falls back to 48px for Amazon Pay when no Amazon Pay height is set', () => {
			// The shared button height differs from the Amazon Pay default to prove
			// Amazon Pay does not inherit the Apple/Google Pay size.
			window.wc_stripe_express_checkout_params = {
				button: { height: '40' },
			};

			expect(
				getExpressCheckoutButtonStyleSettings(
					EXPRESS_PAYMENT_METHOD_SETTING_AMAZON_PAY
				).buttonHeight
			).toBe( 48 );
		} );
	} );
} );
