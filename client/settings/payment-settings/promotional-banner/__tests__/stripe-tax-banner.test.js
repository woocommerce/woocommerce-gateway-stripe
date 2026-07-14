import { act, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { StripeTaxBanner } from '../stripe-tax-banner';
import apiFetch from '@wordpress/api-fetch';
import { recordEvent } from 'wcstripe/tracking';

jest.mock( '@wordpress/api-fetch' );

jest.mock( 'wcstripe/tracking', () => ( {
	recordEvent: jest.fn(),
} ) );

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
				/Automatically calculate and collect sales tax, value-added tax \(VAT\), and goods and services tax \(GST\) wherever you sell./
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
		const { getByText } = render(
			<StripeTaxBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		const activateButton = getByText( 'Get Stripe Tax' );

		expect( activateButton ).toHaveAttribute(
			'href',
			'https://woocommerce.com/products/stripe-tax/'
		);

		await act( async () => {
			await userEvent.click( activateButton );
		} );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_stripe_tax_banner_button_click',
			{}
		);
	} );
} );
