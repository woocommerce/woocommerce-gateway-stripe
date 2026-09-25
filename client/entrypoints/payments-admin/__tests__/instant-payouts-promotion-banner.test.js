import React from 'react';
import { act, fireEvent, render, screen } from '@testing-library/react';
import InstantPayoutsPromotionBanner from '../instant-payouts-promotion-banner';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch' );

describe( 'InstantPayoutsPromotionBanner', () => {
	beforeEach( () => {
		global.window.wc_stripe_admin_payments_params = {
			locale: 'en-US',
			noDecimalCurrencies: [],
			threeDecimalCurrencies: [],
		};
	} );

	afterEach( () => {
		delete global.window.wc_stripe_admin_payments_params;
	} );

	it.each( [
		[
			'one amount',
			[ { amount: 10000, currency: 'usd' } ],
			'You currently have $100.00 available.',
		],
		[
			'two amounts',
			[
				{ amount: 10000, currency: 'usd' },
				{ amount: 20000, currency: 'usd' },
			],
			'You currently have $100.00 and $200.00 available.',
		],
		[
			'three amounts',
			[
				{ amount: 10000, currency: 'usd' },
				{ amount: 20000, currency: 'usd' },
				{ amount: 30000, currency: 'usd' },
			],
			'You currently have $100.00, $200.00 and $300.00 available.',
		],
	] )(
		'formats %s with the correct separators',
		async ( _, amounts, expected ) => {
			apiFetch.mockResolvedValue( { instant_available: amounts } );

			render( <InstantPayoutsPromotionBanner /> );

			expect( await screen.findByText( expected ) ).toBeInTheDocument();
		}
	);

	it( 'shows the banner without instant payouts when the flag is enabled', async () => {
		apiFetch.mockResolvedValue( {} );

		render(
			<InstantPayoutsPromotionBanner
				showBannerIfNoInstantPayoutsAvailable
			/>
		);

		expect(
			await screen.findByText(
				'You currently have no instant payouts available.'
			)
		).toBeInTheDocument();
	} );

	it( 'hides the banner without instant payouts when the flag is disabled', async () => {
		let resolveRequest;
		apiFetch.mockReturnValue(
			new Promise( ( resolve ) => {
				resolveRequest = resolve;
			} )
		);

		const { container } = render(
			<InstantPayoutsPromotionBanner
				showBannerIfNoInstantPayoutsAvailable={ false }
			/>
		);

		expect( container ).toBeEmptyDOMElement();

		await act( async () => resolveRequest( {} ) );

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'shows the REST API error message', async () => {
		apiFetch.mockRejectedValue(
			new Error( 'Unable to load instant payouts.' )
		);

		const { container } = render( <InstantPayoutsPromotionBanner /> );
		await screen.findByRole( 'button', { name: 'Close' } );

		expect(
			container.querySelector( '.wcstripe-inline-notice' )
		).toHaveTextContent( 'Unable to load instant payouts.' );
	} );

	it( 'dismisses the REST API error message', async () => {
		apiFetch.mockRejectedValue(
			new Error( 'Unable to load instant payouts.' )
		);

		const { container } = render( <InstantPayoutsPromotionBanner /> );
		const closeButton = await screen.findByRole( 'button', {
			name: 'Close',
		} );

		fireEvent.click( closeButton );

		expect(
			container.querySelector( '.wcstripe-inline-notice' )
		).not.toBeInTheDocument();
	} );

	it( 'shows the formatted instant payout amounts returned by the API', async () => {
		apiFetch.mockResolvedValue( {
			instant_available: [
				{ amount: 40000, currency: 'usd' },
				{ amount: 30000, currency: 'eur' },
			],
		} );

		render( <InstantPayoutsPromotionBanner /> );

		expect(
			await screen.findByText(
				'You currently have $400.00 and €300.00 available.'
			)
		).toBeInTheDocument();
	} );
} );
