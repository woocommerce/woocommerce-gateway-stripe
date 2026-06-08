import React from 'react';
import { act, render, screen } from '@testing-library/react';
import { Elements, ExpressCheckoutElement } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import ExpressCheckoutPreviewComponent from '../express-checkout-preview-component';
import { useExpressCheckoutEnabledSettings } from 'wcstripe/data';
import { getDefaultBorderRadius } from 'wcstripe/express-checkout/utils';

// `<Notice />` from `@wordpress/components` calls into `@wordpress/a11y` via its
// bundled dependency path; silence the speak() side effect in tests.
const realPathToA11yModule =
	'@wordpress/components/node_modules/@wordpress/a11y';

jest.mock( realPathToA11yModule, () => ( {
	...jest.requireActual( realPathToA11yModule ),
	speak: jest.fn(),
} ) );

jest.mock( '@stripe/react-stripe-js', () => ( {
	Elements: jest.fn( ( { children } ) => (
		<div data-testid="stripe-elements">{ children }</div>
	) ),
	ExpressCheckoutElement: jest.fn( () => (
		<div data-testid="express-checkout-element" />
	) ),
} ) );

jest.mock( '@stripe/stripe-js', () => ( {
	loadStripe: jest.fn(),
} ) );

jest.mock( 'wcstripe/data', () => ( {
	useExpressCheckoutEnabledSettings: jest.fn(),
} ) );

jest.mock( 'wcstripe/express-checkout/utils', () => ( {
	getDefaultBorderRadius: jest.fn(),
} ) );

describe( 'ExpressCheckoutPreviewComponent', () => {
	const globalValues = global.wc_stripe_express_checkout_settings_params;

	const defaultProps = {
		buttonType: 'buy',
		theme: 'dark',
		size: 'default',
	};

	beforeEach( () => {
		useExpressCheckoutEnabledSettings.mockReturnValue( [
			true,
			jest.fn(),
		] );
		getDefaultBorderRadius.mockReturnValue( 4 );
		loadStripe.mockReturnValue( {} );

		global.wc_stripe_express_checkout_settings_params = {
			...globalValues,
			key: 'pk_test_123',
			locale: 'en',
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
		global.wc_stripe_express_checkout_settings_params = globalValues;
	} );

	const getExpressCheckoutElementProps = () => {
		const lastCall =
			ExpressCheckoutElement.mock.calls[
				ExpressCheckoutElement.mock.calls.length - 1
			];
		return lastCall[ 0 ];
	};

	it( 'renders the warning notice when express checkout is disabled', () => {
		useExpressCheckoutEnabledSettings.mockReturnValue( [
			false,
			jest.fn(),
		] );

		render( <ExpressCheckoutPreviewComponent { ...defaultProps } /> );

		expect(
			screen.getByText( /The preview is only available when/ )
		).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'express-checkout-element' )
		).not.toBeInTheDocument();
	} );

	it( 'renders the Stripe ExpressCheckoutElement when enabled', () => {
		render( <ExpressCheckoutPreviewComponent { ...defaultProps } /> );

		expect(
			screen.getByTestId( 'express-checkout-element' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /The preview is only available when/ )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText(
				/Failed to preview the Apple Pay or Google Pay/
			)
		).not.toBeInTheDocument();
	} );

	it( 'initializes Stripe with the publishable key and locale from global params', () => {
		render( <ExpressCheckoutPreviewComponent { ...defaultProps } /> );

		expect( loadStripe ).toHaveBeenCalledWith( 'pk_test_123', {
			locale: 'en',
		} );
	} );

	it( 'passes a payment-mode Elements configuration using the default border radius', () => {
		getDefaultBorderRadius.mockReturnValue( 8 );

		render( <ExpressCheckoutPreviewComponent { ...defaultProps } /> );

		const elementsProps = Elements.mock.calls.at( -1 )[ 0 ];
		expect( elementsProps.options ).toEqual(
			expect.objectContaining( {
				mode: 'payment',
				amount: 1000,
				currency: 'usd',
				paymentMethodTypes: [ 'card' ],
			} )
		);
		expect( elementsProps.options.appearance.variables.borderRadius ).toBe(
			'8px'
		);
	} );

	it( 'maps the "default" buttonType to "plain" for both Apple Pay and Google Pay', () => {
		render(
			<ExpressCheckoutPreviewComponent
				{ ...defaultProps }
				buttonType="default"
			/>
		);

		const { options } = getExpressCheckoutElementProps();
		expect( options.buttonType ).toEqual( {
			googlePay: 'plain',
			applePay: 'plain',
		} );
	} );

	it.each( [
		[ 'buy', 'buy' ],
		[ 'book', 'book' ],
		[ 'donate', 'donate' ],
		[ 'check-out', 'check-out' ],
	] )(
		'passes through the "%s" buttonType unchanged',
		( buttonType, expected ) => {
			render(
				<ExpressCheckoutPreviewComponent
					{ ...defaultProps }
					buttonType={ buttonType }
				/>
			);

			const { options } = getExpressCheckoutElementProps();
			expect( options.buttonType ).toEqual( {
				googlePay: expected,
				applePay: expected,
			} );
		}
	);

	it.each( [
		[ 'dark', { googlePay: 'black', applePay: 'black' } ],
		[ 'light', { googlePay: 'white', applePay: 'white' } ],
		[ 'light-outline', { googlePay: 'white', applePay: 'white-outline' } ],
		[ 'unknown-theme', { googlePay: 'black', applePay: 'black' } ],
	] )(
		'maps the "%s" theme to the right Stripe theme',
		( theme, expected ) => {
			render(
				<ExpressCheckoutPreviewComponent
					{ ...defaultProps }
					theme={ theme }
				/>
			);

			const { options } = getExpressCheckoutElementProps();
			expect( options.buttonTheme ).toEqual( expected );
		}
	);

	it.each( [
		[ 'small', 40 ],
		[ 'default', 48 ],
	] )(
		'maps the "%s" size to a %dpx button height',
		( size, expectedHeight ) => {
			render(
				<ExpressCheckoutPreviewComponent
					{ ...defaultProps }
					size={ size }
				/>
			);

			const { options } = getExpressCheckoutElementProps();
			expect( options.buttonHeight ).toBe( expectedHeight );
		}
	);

	it( 'clamps the "large" size button height to the 55px Stripe maximum', () => {
		render(
			<ExpressCheckoutPreviewComponent { ...defaultProps } size="large" />
		);

		const { options } = getExpressCheckoutElementProps();
		// large maps to 56, which gets clamped to the 55 upper bound.
		expect( options.buttonHeight ).toBe( 55 );
	} );

	it( 'falls back to the 48px default height when given an unknown size', () => {
		render(
			<ExpressCheckoutPreviewComponent
				{ ...defaultProps }
				size="unknown-size"
			/>
		);

		const { options } = getExpressCheckoutElementProps();
		expect( options.buttonHeight ).toBe( 48 );
	} );

	it( 'only enables Google Pay and Apple Pay; Link, Amazon Pay and Klarna are disabled', () => {
		render( <ExpressCheckoutPreviewComponent { ...defaultProps } /> );

		const { options } = getExpressCheckoutElementProps();
		expect( options.paymentMethods ).toEqual( {
			link: 'never',
			googlePay: 'always',
			applePay: 'always',
			amazonPay: 'never',
			klarna: 'never',
		} );
	} );

	it( 'switches to the error notice when onReady reports no available payment methods', () => {
		render( <ExpressCheckoutPreviewComponent { ...defaultProps } /> );

		const { onReady } = getExpressCheckoutElementProps();

		act( () => {
			onReady( { availablePaymentMethods: null } );
		} );

		expect(
			screen.getByText(
				/Failed to preview the Apple Pay or Google Pay button/
			)
		).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'express-checkout-element' )
		).not.toBeInTheDocument();
	} );

	it( 'keeps rendering the button when onReady reports available payment methods', () => {
		render( <ExpressCheckoutPreviewComponent { ...defaultProps } /> );

		const { onReady } = getExpressCheckoutElementProps();

		act( () => {
			onReady( { availablePaymentMethods: { applePay: true } } );
		} );

		expect(
			screen.getByTestId( 'express-checkout-element' )
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				/Failed to preview the Apple Pay or Google Pay button/
			)
		).not.toBeInTheDocument();
	} );

	it( 'sets the wrapper minHeight to match the requested button size', () => {
		render(
			<ExpressCheckoutPreviewComponent { ...defaultProps } size="small" />
		);

		const wrapper = screen.getByTestId( 'stripe-elements' ).parentElement;
		expect( wrapper ).toHaveStyle( { minHeight: '40px', width: '100%' } );
	} );
} );
