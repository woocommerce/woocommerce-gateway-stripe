import { act, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { StripeTaxBanner } from '../stripe-tax-banner';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch' );

describe( 'Stripe Tax banner', () => {
	const setShowPromotionalBanner = jest.fn();

	beforeEach( () => {
		apiFetch.mockImplementation(
			jest.fn( () => Promise.resolve( { data: {} } ) )
		);
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should render the Stripe Tax banner', () => {
		const { getByText } = render(
			<StripeTaxBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		expect(
			getByText( 'Automate tax compliance with Stripe Tax' )
		).toBeInTheDocument();
		expect(
			getByText(
				/Automatically calculate and collect sales tax, VAT, and GST wherever you sell./
			)
		).toBeInTheDocument();
	} );

	it( 'should make an API call to dismiss the banner on button click', async () => {
		const dismissNoticeMock = jest.fn( () =>
			Promise.resolve( { data: {} } )
		);
		apiFetch.mockImplementation( dismissNoticeMock );

		const { getByText } = render(
			<StripeTaxBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		const dismissButton = getByText( 'Dismiss' );

		await act( async () => {
			await userEvent.click( dismissButton );
		} );
		expect( dismissNoticeMock ).toHaveBeenCalled();
	} );

	it( 'should open the main page when clicking the "Get Stripe Tax" button', async () => {
		// Keep the original function at hand.
		const assign = window.location.assign;

		Object.defineProperty( window, 'location', {
			value: { assign: jest.fn() },
		} );

		const { getByText } = render(
			<StripeTaxBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		const activateButton = getByText( 'Get Stripe Tax' );

		await act( async () => {
			await userEvent.click( activateButton );
		} );

		expect( window.location.assign ).toHaveBeenCalledWith(
			'https://woocommerce.com/products/stripe-tax/'
		);

		// Set the original function back to keep further tests working as expected.
		Object.defineProperty( window, 'location', {
			value: { assign },
		} );
	} );
} );
