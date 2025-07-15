import { act, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { OCPromotionBanner } from '../oc-promotion-banner';

jest.mock( '@wordpress/api-fetch' );

describe( 'OC promotional banner', () => {
	const setShowPromotionalBanner = jest.fn();

	beforeEach( () => {
		apiFetch.mockImplementation(
			jest.fn( () => Promise.resolve( { data: {} } ) )
		);
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should render the OC promotional banner', () => {
		const { getByText } = render(
			<OCPromotionBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		expect(
			getByText(
				"Increase conversions with Stripe's Optimized Checkout Suite"
			)
		).toBeInTheDocument();
		expect(
			getByText(
				'Optimize your checkout for more sales by automatically displaying the most relevant payment methods for each customer.'
			)
		).toBeInTheDocument();
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
			<OCPromotionBanner
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
			<OCPromotionBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		const link = getByText( 'Learn more' );

		expect( link ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/stripe/admin-experience/optimized-checkout-suite/'
		);
	} );
} );
