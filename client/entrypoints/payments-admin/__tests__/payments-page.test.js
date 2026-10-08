import React, { act } from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import PaymentsPage from '../payments-page';
import apiFetch from '@wordpress/api-fetch';
import { dismissNotice } from 'wcstripe/utils';

jest.mock( '@wordpress/api-fetch' );
jest.mock( 'wcstripe/utils', () => ( { dismissNotice: jest.fn() } ) );
jest.mock( '../payouts-table', () => () => null );
jest.mock( '@wordpress/dataviews/build-style/style.css', () => ( {} ) );

// Mock @wordpress/ui and @wordpress/admin-ui components as Jest currently can't
// process some *.mjs files from the @wordpress/theme package.
const passthroughDiv = ( { children } ) => <div>{ children }</div>;

jest.mock( '@wordpress/ui', () => ( {
	LinkButton: ( { href, children } ) => <a href={ href }>{ children }</a>,
	Notice: {
		Root: ( { children } ) => <div data-testid="banner">{ children }</div>,
		Title: passthroughDiv,
		Description: passthroughDiv,
		Actions: passthroughDiv,
		ActionLink: ( { href, children } ) => <a href={ href }>{ children }</a>,
		CloseIcon: ( { label, onClick } ) => (
			<button onClick={ onClick }>{ label }</button>
		),
	},
} ) );

jest.mock( '@wordpress/admin-ui', () => ( {
	Page: ( { actions, children } ) => (
		<div>
			<div data-testid="page-actions">{ actions }</div>
			{ children }
		</div>
	),
} ) );

const renderPage = async () => {
	await act( async () => {
		render( <PaymentsPage /> );
	} );
};

describe( 'PaymentsPage', () => {
	beforeEach( () => {
		global.window.wc_stripe_admin_payments_params = {
			locale: 'en-US',
			noDecimalCurrencies: [],
			threeDecimalCurrencies: [],
			defaultAccountCurrency: 'usd',
		};
	} );

	afterEach( () => {
		delete global.window.wc_stripe_admin_payments_params;
		jest.clearAllMocks();
	} );

	it.each( [
		[
			'one amount from default currency',
			[ { amount: 10000, currency: 'usd' } ],
			'usd',
			'You currently have $100.00 available.',
		],
		[
			'three amounts, one from default currency',
			[
				{ amount: 10000, currency: 'eur' },
				{ amount: 20000, currency: 'cad' },
				{ amount: 30000, currency: 'usd' },
			],
			'usd',
			'You currently have $300.00 available.',
		],
		[
			'a non-USD default currency',
			[
				{ amount: 40000, currency: 'usd' },
				{ amount: 32198, currency: 'eur' },
			],
			'eur',
			'You currently have €321.98 available.',
		],
	] )(
		'shows the banner with the expected amount for %s',
		async ( _, amounts, defaultCurrency, expected ) => {
			global.window.wc_stripe_admin_payments_params.defaultAccountCurrency =
				defaultCurrency;
			apiFetch.mockResolvedValue( { instant_available: amounts } );

			await renderPage();

			expect(
				screen.getByText( ( content ) => content.includes( expected ) )
			).toBeInTheDocument();
			expect(
				screen.getByTestId( 'page-actions' )
			).toBeEmptyDOMElement();
		}
	);

	it.each( [
		[ 'no instant payouts', {} ],
		[
			'no amount in the default currency',
			{
				instant_available: [
					{ amount: 10000, currency: 'eur' },
					{ amount: 20000, currency: 'cad' },
				],
			},
		],
	] )(
		'shows neither the banner nor the header action with %s',
		async ( _, balance ) => {
			apiFetch.mockResolvedValue( balance );

			await renderPage();

			expect( screen.queryByTestId( 'banner' ) ).not.toBeInTheDocument();
			expect(
				screen.getByTestId( 'page-actions' )
			).toBeEmptyDOMElement();
		}
	);

	it( 'shows neither the banner nor the header action without a default account currency', async () => {
		global.window.wc_stripe_admin_payments_params.defaultAccountCurrency =
			'';
		apiFetch.mockResolvedValue( {
			instant_available: [ { amount: 10000, currency: 'usd' } ],
		} );

		await renderPage();

		expect( screen.queryByTestId( 'banner' ) ).not.toBeInTheDocument();
		expect( screen.getByTestId( 'page-actions' ) ).toBeEmptyDOMElement();
	} );

	it( 'shows neither the banner nor the header action when the balance request fails', async () => {
		apiFetch.mockRejectedValue( new Error( 'Request failed' ) );

		await renderPage();

		expect( screen.queryByTestId( 'banner' ) ).not.toBeInTheDocument();
		expect( screen.getByTestId( 'page-actions' ) ).toBeEmptyDOMElement();
	} );

	it.each( [
		[ 'test', false, 'https://dashboard.stripe.com/test/payouts/' ],
		[ 'test', undefined, 'https://dashboard.stripe.com/test/payouts/' ],
		[ 'live', true, 'https://dashboard.stripe.com/payouts/' ],
	] )(
		'links to the %s payouts dashboard when livemode is %s',
		async ( _, livemode, expectedUrl ) => {
			apiFetch.mockResolvedValue( {
				livemode,
				instant_available: [ { amount: 10000, currency: 'usd' } ],
			} );

			await renderPage();

			expect(
				screen.getByRole( 'link', { name: 'Pay out instantly' } )
			).toHaveAttribute( 'href', expectedUrl );
		}
	);

	it( 'moves the call to action into the page header when the banner is dismissed', async () => {
		apiFetch.mockResolvedValue( {
			instant_available: [ { amount: 10000, currency: 'usd' } ],
		} );

		await renderPage();

		fireEvent.click( screen.getByRole( 'button', { name: 'Dismiss' } ) );

		expect( dismissNotice ).toHaveBeenCalledWith(
			'wc_stripe_show_instant_payouts_banner'
		);
		expect( screen.queryByTestId( 'banner' ) ).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'View Instant Payouts' } )
		).toHaveAttribute(
			'href',
			'https://dashboard.stripe.com/test/payouts/'
		);
	} );

	it( 'shows only the header action when the banner was already dismissed', async () => {
		global.window.wc_stripe_admin_payments_params.isInstantPayoutsBannerDismissed =
			'yes';
		apiFetch.mockResolvedValue( {
			instant_available: [ { amount: 10000, currency: 'usd' } ],
		} );

		await renderPage();

		expect( screen.queryByTestId( 'banner' ) ).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'View Instant Payouts' } )
		).toBeInTheDocument();
	} );
} );
