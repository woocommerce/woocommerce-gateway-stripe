import React from 'react';
import { render, screen } from '@testing-library/react';
import { useStripe } from '@stripe/react-stripe-js';
import ExpressCheckoutButtonPreview from '../express-checkout-button-preview';
import { shouldUseGooglePayBrand } from '../utils/utils';

// `<Notice />` from `@wordpress/components` calls into `@wordpress/a11y` to
// announce the message; silence the speak() side effect in tests.
jest.mock( '@wordpress/a11y', () => ( {
	...jest.requireActual( '@wordpress/a11y' ),
	speak: jest.fn(),
} ) );

jest.mock( '../utils/utils', () => ( {
	shouldUseGooglePayBrand: jest.fn(),
} ) );

jest.mock( '@stripe/react-stripe-js', () => ( {
	PaymentRequestButtonElement: jest
		.fn()
		.mockReturnValue( <button type="submit">Stripe button mock</button> ),
	useStripe: jest.fn(),
} ) );

jest.mock( 'wcstripe/data', () => ( {
	useExpressCheckoutButtonType: jest.fn().mockReturnValue( [ 'buy' ] ),
	useExpressCheckoutButtonSize: jest.fn().mockReturnValue( [ 'default' ] ),
	useExpressCheckoutButtonTheme: jest.fn().mockReturnValue( [ 'dark' ] ),
} ) );

describe( 'ExpressCheckoutButtonPreview', () => {
	const canMakePaymentMock = jest.fn();

	beforeEach( () => {
		shouldUseGooglePayBrand.mockReturnValue( true );
		useStripe.mockReturnValue( {
			paymentRequest: () => ( {
				canMakePayment: canMakePaymentMock,
			} ),
		} );
		canMakePaymentMock.mockResolvedValue( {} );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'displays Google Chrome and Google Pay when page is in Safari', async () => {
		shouldUseGooglePayBrand.mockReturnValue( false );

		render( <ExpressCheckoutButtonPreview /> );

		expect(
			await screen.findByText(
				'To preview the Google Pay button, view this page in the Google Chrome browser.'
			)
		).toBeInTheDocument();
		expect( screen.queryByText( /Safari/ ) ).not.toBeInTheDocument();
	} );

	it( 'displays Safari Apple Pay when page is in Google Chrome', async () => {
		shouldUseGooglePayBrand.mockReturnValue( true );

		render( <ExpressCheckoutButtonPreview /> );

		expect(
			await screen.findByText(
				'To preview the Apple Pay button, view this page in the Safari browser.'
			)
		).toBeInTheDocument();
		expect( screen.queryByText( /Google Chrome/ ) ).not.toBeInTheDocument();
	} );

	it( 'displays an info notice if stripe is falsy', async () => {
		useStripe.mockReturnValue( null );

		render( <ExpressCheckoutButtonPreview /> );

		expect(
			screen.queryByText( 'Stripe button mock' )
		).not.toBeInTheDocument();
		expect(
			await screen.findByText(
				/To preview the buttons, ensure your device is configured/
			)
		).toBeInTheDocument();
	} );

	it( 'displays an info notice if stripe fails to load', async () => {
		canMakePaymentMock.mockResolvedValue( null );
		render( <ExpressCheckoutButtonPreview /> );

		expect(
			await screen.findByText(
				/To preview the buttons, ensure your device is configured/
			)
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'Stripe button mock' )
		).not.toBeInTheDocument();
	} );

	it( 'displays the payment button when stripe is loaded', async () => {
		render( <ExpressCheckoutButtonPreview /> );

		expect(
			await screen.findByText( 'Stripe button mock' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /ensure your device is configured/ )
		).not.toBeInTheDocument();
	} );
} );
