import { act, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { BNPLPromotionBanner } from '../bnpl-promotion-banner';

jest.mock( '@wordpress/api-fetch' );

describe( 'BNPL promotional banner', () => {
	const setShowPromotionalBanner = jest.fn();

	beforeEach( () => {
		apiFetch.mockImplementation(
			jest.fn( () => Promise.resolve( { data: {} } ) )
		);
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should render the BNPL promotional banner', () => {
		const { getByText } = render(
			<BNPLPromotionBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		expect(
			getByText( 'Offer more ways to pay with Buy Now, Pay Later' )
		).toBeInTheDocument();
		expect(
			getByText(
				/Flexible pay-over-time options can boost revenue by up to 14%*./
			)
		).toBeInTheDocument();
		expect(
			getByText(
				/Affirm and Klarna payments are auto-enabled with Stripe for eligible merchants./
			)
		).toBeInTheDocument();
		expect( getByText( '*Source: Stripe 2024' ) ).toBeInTheDocument();
	} );

	it( 'should make an API call to dismiss the banner on button click', async () => {
		// Keep the original function.
		const reload = window.location.reload;
		Object.defineProperty( window, 'location', {
			value: { reload: jest.fn() },
		} );

		const dismissNoticeMock = jest.fn( () =>
			Promise.resolve( { data: {} } )
		);
		apiFetch.mockImplementation( dismissNoticeMock );

		const { getByText } = render(
			<BNPLPromotionBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		const dismissButton = getByText( 'Dismiss' );

		await act( async () => {
			await userEvent.click( dismissButton );
		} );
		expect( dismissNoticeMock ).toHaveBeenCalled();

		// Set the original function back to keep further tests working as expected.
		Object.defineProperty( window, 'location', {
			value: { reload },
		} );
	} );

	it( 'link should contain the correct attributes', async () => {
		const { getByText } = render(
			<BNPLPromotionBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		const link = getByText( 'Learn more' );

		expect( link ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/stripe/setup-and-configuration/additional-payment-methods/'
		);
	} );
} );
