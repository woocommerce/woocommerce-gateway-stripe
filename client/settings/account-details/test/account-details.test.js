import { render, screen } from '@testing-library/react';
import AccountDetails from '..';
import { useAccount, useGetCapabilities } from 'wcstripe/data/account';

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
	useGetCapabilities: jest.fn(),
} ) );

describe( 'AccountDetails', () => {
	it( 'renders enabled payments and deposits on account', () => {
		useGetCapabilities.mockReturnValue( {
			card_payments: 'active',
		} );
		useAccount.mockReturnValue( {
			data: {
				account: {
					settings: {
						payouts: {
							schedule: { interval: 'daily', delay_days: 2 },
						},
					},
					payouts_enabled: true,
				},
			},
		} );
		render( <AccountDetails /> );

		expect(
			screen.queryByText( 'Error determining the connection status' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( /may be disabled/i )
		).not.toBeInTheDocument();
	} );

	it( 'renders an error message when the account data is not available', () => {
		useGetCapabilities.mockReturnValue( {} );
		useAccount.mockReturnValue( {
			data: {},
		} );
		render( <AccountDetails /> );

		expect(
			screen.queryByText( 'Error determining the connection status.' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /may be disabled/i )
		).not.toBeInTheDocument();
	} );

	it( 'renders disabled deposits and payments on account', () => {
		useGetCapabilities.mockReturnValue( {
			card_payments: 'disabled',
		} );
		useAccount.mockReturnValue( {
			data: {
				account: {
					settings: {
						payouts: {},
					},
					payouts_enabled: false,
				},
			},
		} );
		render( <AccountDetails /> );

		expect(
			screen.queryByText( 'Error determining the connection status' )
		).not.toBeInTheDocument();
		expect( screen.queryByText( /may be disabled/i ) ).toBeInTheDocument();
	} );
} );
