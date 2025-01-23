import React from 'react';
import { render, screen } from '@testing-library/react';
import { useStripe } from '@stripe/react-stripe-js';
import AmazonPayButtonPreview from '../amazon-pay-button-preview';

// We need to mock the actual module being used by `<Notice />` in the `@wordpress/components` module
const realPathToA11yModule =
	'@wordpress/components/node_modules/@wordpress/a11y';

jest.mock( realPathToA11yModule, () => ( {
	...jest.requireActual( realPathToA11yModule ),
	speak: jest.fn(),
} ) );

jest.mock( '@stripe/react-stripe-js', () => ( {
	PaymentRequestButtonElement: jest
		.fn()
		.mockReturnValue(
			<button type="submit">Amazon Pay Button Mock</button>
		),
	useStripe: jest.fn(),
} ) );

jest.mock( 'wcstripe/data', () => ( {
	useAmazonPayButtonSize: jest.fn().mockReturnValue( [ 'default' ] ),
} ) );

describe( 'AmazonPayButtonPreview', () => {
	const canMakePaymentMock = jest.fn();

	beforeEach( () => {
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

	it( 'displays an info notice if stripe is falsy', async () => {
		useStripe.mockReturnValue( null );

		render( <AmazonPayButtonPreview /> );

		expect(
			screen.queryByText( 'Amazon Pay Button Mock' )
		).not.toBeInTheDocument();
		expect(
			await screen.findByText(
				/ensure your device is configured to accept Amazon Pay/
			)
		).toBeInTheDocument();
	} );

	it( 'displays an info notice if stripe fails to load', async () => {
		canMakePaymentMock.mockResolvedValue( null );
		render( <AmazonPayButtonPreview /> );

		expect(
			await screen.findByText(
				/ensure your device is configured to accept Amazon Pay/
			)
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'Amazon Pay Button Mock' )
		).not.toBeInTheDocument();
	} );

	it( 'displays the payment button when stripe is loaded', async () => {
		render( <AmazonPayButtonPreview /> );

		expect(
			await screen.findByText( 'Amazon Pay Button Mock' )
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				/ensure your device is configured to accept Amazon Pay/
			)
		).not.toBeInTheDocument();
	} );
} );
