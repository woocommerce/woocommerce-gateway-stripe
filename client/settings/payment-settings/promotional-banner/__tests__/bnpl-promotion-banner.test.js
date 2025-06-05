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
		const { getByText, getByTestId } = render(
			<BNPLPromotionBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		expect(
			getByText( 'Offer more ways to pay with Buy Now, Pay Later' )
		).toBeInTheDocument();
		expect( getByTestId( 'intro-bnpl' ) ).toBeInTheDocument();
		expect( getByText( '*Source: Stripe 2024' ) ).toBeInTheDocument();
	} );

	it( 'should make an API call to dismiss the banner on button click', async () => {
		const dismissNoticeMock = jest.fn( () =>
			Promise.resolve( { data: {} } )
		);
		apiFetch.mockImplementation( dismissNoticeMock );

		const { getByTestId } = render(
			<BNPLPromotionBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		const dismissButton = getByTestId( 'dismiss' );

		await act( async () => {
			await userEvent.click( dismissButton );
		} );
		expect( dismissNoticeMock ).toHaveBeenCalled();
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
