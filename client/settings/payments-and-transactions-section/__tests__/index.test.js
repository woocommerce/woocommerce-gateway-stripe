import { fireEvent, render, screen } from '@testing-library/react';
import PaymentsAndTransactionsSection from '..';
import {
	useManualCapture,
	useSavedCards,
	useIsShortAccountStatementEnabled,
	useSeparateCardForm,
	useAccountStatementDescriptor,
	useShortAccountStatementDescriptor,
} from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useManualCapture: jest.fn(),
	useSavedCards: jest.fn(),
	useIsShortAccountStatementEnabled: jest.fn(),
	useSeparateCardForm: jest.fn(),
	useAccountStatementDescriptor: jest.fn(),
	useShortAccountStatementDescriptor: jest.fn(),
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
} );
