import React from 'react';
import { act, render, screen } from '@testing-library/react';
import { Elements, ExpressCheckoutElement } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import ExpressCheckoutPreview from '..';
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

jest.mock( 'wcstripe/express-checkout/utils', () => ( {
	getDefaultBorderRadius: jest.fn(),
} ) );

describe( 'ExpressCheckoutPreview', () => {
	// The express-checkout-settings page passes the full prop set: button
	// type/theme plus the express-checkout-enabled gate.
	const eceProps = {
		params: { key: 'pk_test_123', locale: 'en' },
		paymentMethodTypes: [ 'card' ],
		paymentMethods: {
			link: 'never',
			googlePay: 'always',
			applePay: 'always',
			amazonPay: 'never',
			klarna: 'never',
		},
		buttonType: 'buy',
		theme: 'dark',
		size: 'default',
		requireExpressCheckoutEnabled: true,
		isExpressCheckoutEnabled: true,
		errorMessage: 'Failed to preview the Apple Pay or Google Pay button.',
	};

	// The Amazon Pay / Link pages pass no button type/theme and no gate.
	const amazonProps = {
		params: { key: 'pk_test_123', locale: 'en' },
		paymentMethodTypes: [ 'amazon_pay' ],
		paymentMethods: {
			amazonPay: 'auto',
			link: 'never',
			googlePay: 'never',
			applePay: 'never',
			klarna: 'never',
		},
		size: 'default',
		errorMessage: 'Failed to preview the Amazon Pay button.',
	};

	beforeEach( () => {
		getDefaultBorderRadius.mockReturnValue( 4 );
		loadStripe.mockReturnValue( {} );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	const getExpressCheckoutElementProps = () => {
		const lastCall =
			ExpressCheckoutElement.mock.calls[
				ExpressCheckoutElement.mock.calls.length - 1
			];
		return lastCall[ 0 ];
	};

	it( 'renders the warning notice when express checkout is disabled and the gate is required', () => {
		render(
			<ExpressCheckoutPreview
				{ ...eceProps }
				isExpressCheckoutEnabled={ false }
			/>
		);

		expect(
			screen.getByText( /The preview is only available when/ )
		).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'express-checkout-element' )
		).not.toBeInTheDocument();
	} );

	it( 'ignores the express-checkout-enabled state when the gate is not required', () => {
		// amazonProps omits requireExpressCheckoutEnabled, so a disabled state
		// must not suppress the preview on the Amazon Pay / Link pages.
		render(
			<ExpressCheckoutPreview
				{ ...amazonProps }
				isExpressCheckoutEnabled={ false }
			/>
		);

		expect(
			screen.queryByText( /The preview is only available when/ )
		).not.toBeInTheDocument();
		expect(
			screen.getByTestId( 'express-checkout-element' )
		).toBeInTheDocument();
	} );

	it( 'renders the Stripe ExpressCheckoutElement when enabled', () => {
		render( <ExpressCheckoutPreview { ...eceProps } /> );

		expect(
			screen.getByTestId( 'express-checkout-element' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /The preview is only available when/ )
		).not.toBeInTheDocument();
	} );

	it( 'initializes Stripe with the publishable key and locale from params', () => {
		render( <ExpressCheckoutPreview { ...eceProps } /> );

		expect( loadStripe ).toHaveBeenCalledWith( 'pk_test_123', {
			locale: 'en',
		} );
	} );

	it( 'passes a payment-mode Elements configuration using the default border radius and provided method types', () => {
		getDefaultBorderRadius.mockReturnValue( 8 );

		render( <ExpressCheckoutPreview { ...amazonProps } /> );

		const elementsProps = Elements.mock.calls.at( -1 )[ 0 ];
		expect( elementsProps.options ).toEqual(
			expect.objectContaining( {
				mode: 'payment',
				amount: 1000,
				currency: 'usd',
				paymentMethodTypes: [ 'amazon_pay' ],
			} )
		);
		expect( elementsProps.options.appearance.variables.borderRadius ).toBe(
			'8px'
		);
	} );

	it( 'maps the "default" buttonType to "plain" for both Apple Pay and Google Pay', () => {
		render(
			<ExpressCheckoutPreview { ...eceProps } buttonType="default" />
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
				<ExpressCheckoutPreview
					{ ...eceProps }
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
				<ExpressCheckoutPreview { ...eceProps } theme={ theme } />
			);

			const { options } = getExpressCheckoutElementProps();
			expect( options.buttonTheme ).toEqual( expected );
		}
	);

	it( 'omits buttonType and buttonTheme when no button type/theme props are supplied', () => {
		render( <ExpressCheckoutPreview { ...amazonProps } /> );

		const { options } = getExpressCheckoutElementProps();
		expect( options.buttonType ).toBeUndefined();
		expect( options.buttonTheme ).toBeUndefined();
	} );

	it.each( [
		[ 'small', 40 ],
		[ 'default', 48 ],
	] )(
		'maps the "%s" size to a %dpx button height',
		( size, expectedHeight ) => {
			render( <ExpressCheckoutPreview { ...eceProps } size={ size } /> );

			const { options } = getExpressCheckoutElementProps();
			expect( options.buttonHeight ).toBe( expectedHeight );
		}
	);

	it( 'clamps the "large" size button height to the 55px Stripe maximum', () => {
		render( <ExpressCheckoutPreview { ...eceProps } size="large" /> );

		const { options } = getExpressCheckoutElementProps();
		// large maps to 56, which gets clamped to the 55 upper bound.
		expect( options.buttonHeight ).toBe( 55 );
	} );

	it( 'falls back to the 48px default height when given an unknown size', () => {
		render(
			<ExpressCheckoutPreview { ...eceProps } size="unknown-size" />
		);

		const { options } = getExpressCheckoutElementProps();
		expect( options.buttonHeight ).toBe( 48 );
	} );

	it( 'forwards the provided paymentMethods flags to the ExpressCheckoutElement', () => {
		render( <ExpressCheckoutPreview { ...eceProps } /> );

		const { options } = getExpressCheckoutElementProps();
		expect( options.paymentMethods ).toEqual( {
			link: 'never',
			googlePay: 'always',
			applePay: 'always',
			amazonPay: 'never',
			klarna: 'never',
		} );
	} );

	it( 'switches to the provided error notice when onReady reports no available payment methods', () => {
		render( <ExpressCheckoutPreview { ...amazonProps } /> );

		const { onReady } = getExpressCheckoutElementProps();

		act( () => {
			onReady( { availablePaymentMethods: null } );
		} );

		expect(
			screen.getByText( /Failed to preview the Amazon Pay button/ )
		).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'express-checkout-element' )
		).not.toBeInTheDocument();
	} );

	it( 'keeps rendering the button when onReady reports available payment methods', () => {
		render( <ExpressCheckoutPreview { ...eceProps } /> );

		const { onReady } = getExpressCheckoutElementProps();

		act( () => {
			onReady( { availablePaymentMethods: { applePay: true } } );
		} );

		expect(
			screen.getByTestId( 'express-checkout-element' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /Failed to preview/ )
		).not.toBeInTheDocument();
	} );

	it( 'sets the wrapper minHeight to match the requested button size', () => {
		render( <ExpressCheckoutPreview { ...eceProps } size="small" /> );

		const wrapper = screen.getByTestId( 'stripe-elements' ).parentElement;
		expect( wrapper ).toHaveStyle( { minHeight: '40px', width: '100%' } );
	} );
} );
