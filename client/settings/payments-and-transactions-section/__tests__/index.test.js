import { fireEvent, render, screen } from '@testing-library/react';
import PaymentsAndTransactionsSection from '..';
import { useAccount } from '../../../data/account';
import {
	useManualCapture,
	useSavedCards,
	useIsShortAccountStatementEnabled,
	useSeparateCardForm,
	useAccountStatementDescriptor,
	useShortAccountStatementDescriptor,
	useGetSavingError,
} from 'wcstripe/data';

jest.mock( '../../../data/account', () => ( {
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
		useAccountStatementDescriptor.mockReturnValue( [
			'WOOTESTING, LTD',
			jest.fn(),
		] );
		useShortAccountStatementDescriptor.mockReturnValue( [
			'WOOTESTING',
			jest.fn(),
		] );
		useGetSavingError.mockReturnValue( null );
		useAccount.mockReturnValue( { data: {} } );
	} );

	it( 'displays the length of the bank statement input', () => {
		const updateAccountStatementDescriptor = jest.fn();
		useAccountStatementDescriptor.mockReturnValue( [
			'WOOTESTING, LTD',
			updateAccountStatementDescriptor,
		] );
		render( <PaymentsAndTransactionsSection /> );

		expect( screen.getByText( '15 / 22' ) ).toBeInTheDocument();

		fireEvent.change( screen.getByLabelText( 'Full bank statement' ), {
			target: { value: 'New Statement Name' },
		} );

		expect( updateAccountStatementDescriptor ).toHaveBeenCalledWith(
			'New Statement Name'
		);
	} );

	it( 'shows the shortened bank statement input', () => {
		useIsShortAccountStatementEnabled.mockReturnValue( [
			true,
			jest.fn(),
		] );
		const updateShortAccountStatementDescriptor = jest.fn();
		useShortAccountStatementDescriptor.mockReturnValue( [
			'WOOTEST',
			updateShortAccountStatementDescriptor,
		] );
		render( <PaymentsAndTransactionsSection /> );

		expect( screen.getByText( '7 / 10' ) ).toBeInTheDocument();

		fireEvent.change(
			screen.getByLabelText( 'Shortened customer bank statement' ),
			{
				target: { value: 'WOOTESTING' },
			}
		);

		expect( updateShortAccountStatementDescriptor ).toHaveBeenCalledWith(
			'WOOTESTING'
		);
	} );

	it( 'shows the full bank statement preview', () => {
		const updateAccountStatementDescriptor = jest.fn();
		const mockValue = 'WOOTESTING, LTD';
		useAccountStatementDescriptor.mockReturnValue( [
			mockValue,
			updateAccountStatementDescriptor,
		] );
		render( <PaymentsAndTransactionsSection /> );

		expect(
			document.querySelector(
				'.full-bank-statement .transaction-detail.description'
			)
		).toHaveTextContent( mockValue );
	} );

	it( 'shows the shortened customer bank statement preview when useIsShortAccountStatementEnabled is true', () => {
		useIsShortAccountStatementEnabled.mockReturnValue( [
			true,
			jest.fn(),
		] );
		const updateShortAccountStatementDescriptor = jest.fn();
		const mockValue = 'WOOTEST';
		useShortAccountStatementDescriptor.mockReturnValue( [
			mockValue,
			updateShortAccountStatementDescriptor,
		] );
		render( <PaymentsAndTransactionsSection /> );

		expect(
			document.querySelector(
				'.shortened-bank-statement .transaction-detail.description'
			)
		).toHaveTextContent( `${ mockValue }* #123456` );
	} );

	it( 'should not show the shortened customer bank statement preview when useIsShortAccountStatementEnabled is false', () => {
		useIsShortAccountStatementEnabled.mockReturnValue( [
			false,
			jest.fn(),
		] );
		const updateShortAccountStatementDescriptor = jest.fn();
		const mockValue = 'WOOTEST';
		useShortAccountStatementDescriptor.mockReturnValue( [
			mockValue,
			updateShortAccountStatementDescriptor,
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

	it( "shows the account's statement descriptor placeholders", () => {
		const mockValue = 'WOOTESTING, LTD';
		const shortenedMockValue = 'WOOTESTING';

		useAccount.mockReturnValue( {
			data: {
				account: {
					settings: { payments: { statement_descriptor: mockValue } },
				},
			},
		} );
		useIsShortAccountStatementEnabled.mockReturnValue( [
			true,
			jest.fn(),
		] );
		render( <PaymentsAndTransactionsSection /> );

		expect(
			screen.queryByText( 'Full bank statement' ).nextElementSibling
		).toHaveAttribute( 'placeholder', mockValue );
		expect(
			screen.queryByText( 'Shortened customer bank statement' )
				.nextElementSibling
		).toHaveAttribute( 'placeholder', shortenedMockValue );
	} );
} );
