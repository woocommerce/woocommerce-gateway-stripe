import { act, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { OCPromotionBanner } from '../oc-promotion-banner';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';

const noticesDispatch = {
	createErrorNotice: jest.fn(),
	createSuccessNotice: jest.fn(),
};

jest.mock( '@wordpress/data' );

jest.mock( '@wordpress/api-fetch' );

describe( 'OC promotional banner', () => {
	const setShowPromotionalBanner = jest.fn();
	const setIsOCEnabled = jest.fn( () => Promise.resolve() );

	beforeEach( () => {
		useDispatch.mockImplementation( ( storeName ) => {
			if ( storeName === 'core/notices' ) {
				return noticesDispatch;
			}

			return {};
		} );
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
				setIsOCEnabled={ setIsOCEnabled }
			/>
		);
		expect(
			getByText(
				"Increase conversion with Stripe's Optimized Checkout Suite"
			)
		).toBeInTheDocument();
		expect(
			getByText(
				/Optimize your checkout experience for more sales by dynamically displaying the most relevant payment methods you've enabled for each customer./
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
				setIsOCEnabled={ setIsOCEnabled }
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

	it( 'should attempt to enable OC when clicking the "Activate now" button', async () => {
		const { getByText } = render(
			<OCPromotionBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
				setIsOCEnabled={ setIsOCEnabled }
			/>
		);
		const activateButton = getByText( 'Activate now' );

		await act( async () => {
			await userEvent.click( activateButton );
		} );
		expect( setIsOCEnabled ).toHaveBeenCalled();
	} );
} );
