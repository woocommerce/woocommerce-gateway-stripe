import React, { act } from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import InstantPayoutsPromotionNotice from '../instant-payouts-promotion-notice';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch' );

// Mock @wordpress/ui components as Jest currently can't process some *.mjs files
// from the @wordpress/theme package.
const passthroughDiv = ( { children } ) => <div>{ children }</div>;

jest.mock( '@wordpress/ui', () => ( {
	Button: ( { children, onClick } ) => (
		<button onClick={ onClick }>{ children }</button>
	),
	Card: {
		Root: passthroughDiv,
		Header: passthroughDiv,
		Title: passthroughDiv,
		Content: passthroughDiv,
	},
	LinkButton: ( { href, children } ) => <a href={ href }>{ children }</a>,
	Stack: passthroughDiv,
} ) );

describe( 'InstantPayoutsPromotionNotice', () => {
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
	} );

	it.each( [
		[
			'one amount from default currency',
			[ { amount: 10000, currency: 'usd' } ],
			'usd',
			'You currently have $100.00 available.',
		],
		[
			'two amounts, one from default currency',
			[
				{ amount: 10000, currency: 'eur' },
				{ amount: 20000, currency: 'usd' },
			],
			'usd',
			'You currently have $200.00 available.',
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
	] )(
		'renders the expected amount with %s',
		async ( _, amounts, defaultCurrency, expected ) => {
			apiFetch.mockResolvedValue( {
				instant_available: amounts,
			} );

			global.window.wc_stripe_admin_payments_params.defaultAccountCurrency =
				defaultCurrency;

			await act( async () => {
				render( <InstantPayoutsPromotionNotice /> );
			} );

			expect( await screen.findByText( expected ) ).toBeInTheDocument();
		}
	);

	it.each( [
		[
			'two amounts, none from default currency',
			[
				{ amount: 10000, currency: 'eur' },
				{ amount: 20000, currency: 'cad' },
			],
			'usd',
		],
		[
			'two amounts, no default currency',
			[
				{ amount: 10000, currency: 'eur' },
				{ amount: 20000, currency: 'cad' },
			],
			'',
		],
	] )(
		'does not render an available message with %s',
		async ( _, amounts, defaultCurrency ) => {
			global.window.wc_stripe_admin_payments_params.defaultAccountCurrency =
				defaultCurrency;

			apiFetch.mockResolvedValue( {
				instant_available: amounts,
			} );

			await act( async () => {
				render( <InstantPayoutsPromotionNotice /> );
			} );

			expect(
				screen.queryByText( 'You currently have' )
			).not.toBeInTheDocument();
		}
	);

	it( 'hides the notice without instant payouts', async () => {
		let resolveRequest;
		apiFetch.mockReturnValue(
			new Promise( ( resolve ) => {
				resolveRequest = resolve;
			} )
		);

		const { container } = render( <InstantPayoutsPromotionNotice /> );

		expect( container ).toBeEmptyDOMElement();

		await act( async () => resolveRequest( {} ) );

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'links to test payouts when the balance payload is not live', async () => {
		apiFetch.mockResolvedValue( {
			livemode: false,
			instant_available: [ { amount: 10000, currency: 'usd' } ],
		} );

		await act( async () => {
			render( <InstantPayoutsPromotionNotice /> );
		} );

		await screen.findByText( 'You currently have $100.00 available.' );

		expect(
			screen.getByRole( 'link', { name: 'Pay out instantly' } )
		).toHaveAttribute(
			'href',
			'https://dashboard.stripe.com/test/payouts/'
		);
	} );

	it( 'links to the live payouts UI when the balance payload is live', async () => {
		apiFetch.mockResolvedValue( {
			livemode: true,
			instant_available: [ { amount: 10000, currency: 'usd' } ],
		} );

		await act( async () => {
			render( <InstantPayoutsPromotionNotice /> );
		} );

		expect(
			screen.getByRole( 'link', { name: 'Pay out instantly' } )
		).toHaveAttribute( 'href', 'https://dashboard.stripe.com/payouts/' );
	} );

	it( 'shows the instant payout amount for the account currency', async () => {
		apiFetch.mockResolvedValue( {
			instant_available: [
				{ amount: 40000, currency: 'usd' },
				{ amount: 30000, currency: 'eur' },
			],
		} );

		await act( async () => {
			render( <InstantPayoutsPromotionNotice /> );
		} );

		expect(
			await screen.findByText( 'You currently have $400.00 available.' )
		).toBeInTheDocument();
	} );

	it( 'shows the instant payout amount for a non-USD account currency', async () => {
		global.window.wc_stripe_admin_payments_params.defaultAccountCurrency =
			'eur';

		apiFetch.mockResolvedValue( {
			instant_available: [
				{ amount: 40000, currency: 'usd' },
				{ amount: 32198, currency: 'eur' },
			],
		} );

		await act( async () => {
			render( <InstantPayoutsPromotionNotice /> );
		} );

		expect(
			await screen.findByText( 'You currently have €321.98 available.' )
		).toBeInTheDocument();
	} );

	it( 'hides the notice when the Dismiss button is clicked', async () => {
		apiFetch.mockResolvedValue( {
			instant_available: [ { amount: 10000, currency: 'usd' } ],
		} );

		const { container } = render( <InstantPayoutsPromotionNotice /> );

		expect(
			await screen.findByText( 'Improve your cash flow' )
		).toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'button', { name: 'Dismiss' } ) );

		expect( container ).toBeEmptyDOMElement();
	} );
} );
