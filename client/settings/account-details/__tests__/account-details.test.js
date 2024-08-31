import { render, screen } from '@testing-library/react';
import AccountDetails from '..';
import { useAccount, useGetCapabilities } from 'wcstripe/data/account';

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
	useGetCapabilities: jest.fn(),
} ) );
jest.mock( 'wcstripe/data', () => ( {
	useTestMode: jest.fn().mockReturnValue( [ false ] ),
} ) );
jest.mock( 'wcstripe/data/account-keys', () => ( {
	useAccountKeysTestWebhookSecret: jest.fn().mockReturnValue( [ '' ] ),
	useAccountKeysWebhookSecret: jest.fn().mockReturnValue( [ '' ] ),
} ) );

describe( 'AccountDetails', () => {
	it( 'renders enabled payments and payouts on account', () => {
		useAccount.mockReturnValue( {
			data: {
				account: {
					settings: {
						payouts: {
							schedule: { interval: 'daily', delay_days: 2 },
						},
					},
					payouts_enabled: true,
					charges_enabled: true,
				},
			},
		} );
		render( <AccountDetails /> );

		expect( screen.queryByText( /error/i ) ).not.toBeInTheDocument();
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

		expect( screen.queryByText( /no longer valid/i ) ).toBeInTheDocument();
		expect(
			screen.queryByText( /may be disabled/i )
		).not.toBeInTheDocument();
	} );

	it( 'renders disabled payouts and payments on account', () => {
		useAccount.mockReturnValue( {
			data: {
				account: {
					settings: {
						payouts: {},
					},
					payouts_enabled: false,
					charges_enabled: false,
				},
			},
		} );
		render( <AccountDetails /> );

		expect( screen.queryByTestId( 'help' ) ).toBeInTheDocument();
	} );

	it( 'renders the warning icon and the paragraph with the "warning" class when there\'s a warning message', () => {
		const mockedWarningMessage = 'Warning: random message';
		useAccount.mockReturnValue( {
			data: {
				account: {
					settings: {
						payouts: {},
					},
					payouts_enabled: false,
					charges_enabled: false,
				},
				webhook_status_message: mockedWarningMessage,
				webhook_status_code: 4,
			},
		} );
		render( <AccountDetails /> );

		expect( screen.queryByTestId( 'warning-icon' ) ).toBeInTheDocument();
		expect(
			screen.queryByText( mockedWarningMessage )
		).toBeInTheDocument();
	} );
} );
