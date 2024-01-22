import { render, screen } from '@testing-library/react';
import PaymentsAndTransactionsSection from '..';
import { useAccount } from 'wcstripe/data/account';
import {
	useManualCapture,
	useSavedCards,
	useIsShortAccountStatementEnabled,
	useSeparateCardForm,
	useAccountStatementDescriptor,
	useShortAccountStatementDescriptor,
	useGetSavingError,
} from 'wcstripe/data';

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
} ) );

jest.mock( 'wcstripe/data', () => ( {
	useManualCapture: jest.fn(),
	useSavedCards: jest.fn(),
	useIsShortAccountStatementEnabled: jest.fn(),
	useSeparateCardForm: jest.fn(),
	useAccountStatementDescriptor: jest.fn(),
	useShortAccountStatementDescriptor: jest.fn(),
	useGetSavingError: jest.fn(),
} ) );

describe( 'PaymentsAndTransactionsSection', () => {
	beforeEach( () => {
		useManualCapture.mockReturnValue( [ true, jest.fn() ] );
		useSavedCards.mockReturnValue( [ true, jest.fn() ] );
		useIsShortAccountStatementEnabled.mockReturnValue( [
			false,
			jest.fn(),
		] );
		useSeparateCardForm.mockReturnValue( [ true, jest.fn() ] );
		useAccount.mockReturnValue( {
			data: {
				account: {
					settings: {
						payments: { statement_descriptor: 'WOOTESTING, LTD' },
						card_payments: {
							statement_descriptor_prefix: 'WOOTEST',
						},
					},
				},
			},
		} );

		useGetSavingError.mockReturnValue( null );
	} );

	it( 'displays the length of the bank statement input', () => {
		render( <PaymentsAndTransactionsSection /> );

		// The default bank statement ("WOOTESTING, LTD") is 15 characters long.
		expect( screen.getByText( '15 / 22' ) ).toBeInTheDocument();
	} );

	it( 'shows the shortened bank statement input', () => {
		useIsShortAccountStatementEnabled.mockReturnValue( [
			true,
			jest.fn(),
		] );

		useAccount.mockReturnValue( {
			data: {
				account: {
					settings: {
						card_payments: {
							statement_descriptor_prefix: 'WOOTEST',
						},
					},
				},
			},
		} );

		render( <PaymentsAndTransactionsSection /> );

		// The default short bank statement ("WOOTEST") is 7 characters long.
		expect( screen.getByText( '7 / 10' ) ).toBeInTheDocument();
	} );

	it( 'shows the full bank statement preview', () => {
		render( <PaymentsAndTransactionsSection /> );

		expect(
			document.querySelector(
				'.full-bank-statement .transaction-detail.description'
			)
		).toHaveTextContent( 'WOOTESTING, LTD' );
	} );

	it( 'shows the shortened customer bank statement preview when useIsShortAccountStatementEnabled is true', () => {
		useIsShortAccountStatementEnabled.mockReturnValue( [
			true,
			jest.fn(),
		] );

		render( <PaymentsAndTransactionsSection /> );

		expect(
			document.querySelector(
				'.shortened-bank-statement .transaction-detail.description'
			)
		).toHaveTextContent( 'WOOTEST* W #123456' );
	} );

	it( 'should not show the shortened customer bank statement preview when useIsShortAccountStatementEnabled is false', () => {
		useIsShortAccountStatementEnabled.mockReturnValue( [
			false,
			jest.fn(),
		] );

		render( <PaymentsAndTransactionsSection /> );

		expect(
			document.querySelector(
				'.shortened-bank-statement .transaction-detail.description'
			)
		).toBe( null );
	} );

	it( 'displays the error message for the statement input', () => {
		useAccountStatementDescriptor.mockReturnValue( [ 'WOO', jest.fn() ] );
		useGetSavingError.mockReturnValue( {
			code: 'rest_invalid_param',
			message: 'Invalid parameter(s): statement_descriptor',
			data: {
				status: 400,
				params: {
					statement_descriptor:
						'Customer bank statement is invalid. No special characters: \' " * &lt; &gt;',
				},
				details: {
					statement_descriptor: {
						code: 'rest_invalid_pattern',
						message:
							'Customer bank statement is invalid. No special characters: \' " * &lt; &gt;',
						data: null,
					},
				},
			},
		} );

		render( <PaymentsAndTransactionsSection /> );

		expect(
			screen.getByText(
				`Customer bank statement is invalid. No special characters: ' " * < >`
			)
		).toBeInTheDocument();
	} );

	it( 'displays the error message for the short statement input', () => {
		useShortAccountStatementDescriptor.mockReturnValue( [
			'WOO',
			jest.fn(),
		] );
		useIsShortAccountStatementEnabled.mockReturnValue( [
			true,
			jest.fn(),
		] );
		useGetSavingError.mockReturnValue( {
			code: 'rest_invalid_param',
			message: 'Invalid parameter(s): short_statement_descriptor',
			data: {
				status: 400,
				params: {
					short_statement_descriptor:
						'Customer bank statement is invalid. No special characters: \' " * &lt; &gt;',
				},
				details: {
					short_statement_descriptor: {
						code: 'rest_invalid_pattern',
						message:
							'Customer bank statement is invalid. No special characters: \' " * &lt; &gt;',
						data: null,
					},
				},
			},
		} );

		render( <PaymentsAndTransactionsSection /> );

		expect(
			screen.getByText(
				`Customer bank statement is invalid. No special characters: ' " * < >`
			)
		).toBeInTheDocument();
	} );
} );
