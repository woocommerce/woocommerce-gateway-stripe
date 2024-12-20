import { render } from '@testing-library/react';
import PaymentsAndTransactionsSection from '..';
import { useAccount } from 'wcstripe/data/account';
import {
	useManualCapture,
	useSavedCards,
	useEnabledPaymentMethodIds,
	useIsShortAccountStatementEnabled,
	useSeparateCardForm,
	useGetSavingError,
} from 'wcstripe/data';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_CASHAPP,
} from 'wcstripe/stripe-utils/constants';

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
} ) );

jest.mock( 'wcstripe/data', () => ( {
	useManualCapture: jest.fn(),
	useSavedCards: jest.fn(),
	useIsShortAccountStatementEnabled: jest.fn(),
	useSeparateCardForm: jest.fn(),
	useGetSavingError: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
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
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD ],
			jest.fn(),
		] );
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

		expect(
			document.querySelector(
				'.full-bank-statement .statement-icon-and-title'
			)
		).toHaveTextContent( 'All Other Payment Methods' );
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

	it( 'should display a third statement preview when Cash App Pay is enabled', () => {
		useIsShortAccountStatementEnabled.mockReturnValue( [
			true,
			jest.fn(),
		] );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD, PAYMENT_METHOD_CASHAPP ],
			jest.fn(),
		] );

		render( <PaymentsAndTransactionsSection /> );

		expect(
			document.querySelector(
				'.shortened-bank-statement .transaction-detail.description'
			)
		).toHaveTextContent( 'WOOTEST* W #123456' );

		expect(
			document.querySelectorAll(
				'.full-bank-statement .statement-icon-and-title'
			)[ 0 ]
		).toHaveTextContent( 'Cash App Payments' );

		expect(
			document.querySelectorAll(
				'.full-bank-statement .statement-icon-and-title'
			)[ 1 ]
		).toHaveTextContent( 'All Other Payment Methods' );
	} );
} );
