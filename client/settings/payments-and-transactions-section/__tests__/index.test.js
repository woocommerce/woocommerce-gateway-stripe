import { render, screen } from '@testing-library/react';
import PaymentsAndTransactionsSection from '..';
import { useAccount } from 'wcstripe/data/account';
import {
	useManualCapture,
	useSavedCards,
	useSepaTokensForIdeal,
	useEnabledPaymentMethodIds,
	useIsShortAccountStatementEnabled,
	useSeparateCardForm,
	useGetSavingError,
	useSepaTokensForBancontact,
} from 'wcstripe/data';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_CASHAPP,
} from 'wcstripe/stripe-utils/constants';

// `InlineNotice` renders `<Notice />`, which calls into `@wordpress/a11y` via its bundled
// dependency path and speaks into a persistent live region that leaks across tests; silence it.
const realPathToA11yModule =
	'@wordpress/components/node_modules/@wordpress/a11y';

jest.mock( realPathToA11yModule, () => ( {
	...jest.requireActual( realPathToA11yModule ),
	speak: jest.fn(),
} ) );

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
} ) );

jest.mock( 'wcstripe/data', () => ( {
	useManualCapture: jest.fn(),
	useSavedCards: jest.fn(),
	useSepaTokensForIdeal: jest.fn(),
	useSepaTokensForBancontact: jest.fn(),
	useIsShortAccountStatementEnabled: jest.fn(),
	useSeparateCardForm: jest.fn(),
	useGetSavingError: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
} ) );

describe( 'PaymentsAndTransactionsSection', () => {
	beforeEach( () => {
		useManualCapture.mockReturnValue( [ true, jest.fn() ] );
		useSavedCards.mockReturnValue( [ true, jest.fn() ] );
		useSepaTokensForIdeal.mockReturnValue( [ true, jest.fn() ] );
		useSepaTokensForBancontact.mockReturnValue( [ true, jest.fn() ] );
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

	it( 'shows the store name when the Stripe account has no statement descriptor', () => {
		useAccount.mockReturnValue( {
			data: {
				account: { settings: { payments: {}, card_payments: {} } },
				default_statement_descriptor: 'My Woo Store',
			},
		} );

		render( <PaymentsAndTransactionsSection /> );

		expect( screen.getByLabelText( 'Full bank statement' ) ).toHaveValue(
			'My Woo Store'
		);
		expect(
			document.querySelector(
				'.full-bank-statement .transaction-detail.description'
			)
		).toHaveTextContent( 'My Woo Store' );
		expect(
			screen.getByText( /so your store name is used/ )
		).toBeInTheDocument();
	} );

	it( 'prefers the Stripe account statement descriptor over the store name', () => {
		useAccount.mockReturnValue( {
			data: {
				account: {
					settings: {
						payments: { statement_descriptor: 'WOOTESTING, LTD' },
						card_payments: {},
					},
				},
				default_statement_descriptor: 'My Woo Store',
			},
		} );

		render( <PaymentsAndTransactionsSection /> );

		expect( screen.getByLabelText( 'Full bank statement' ) ).toHaveValue(
			'WOOTESTING, LTD'
		);
		expect(
			screen.queryByText( /so your store name is used/ )
		).not.toBeInTheDocument();
	} );

	it( 'warns that the store-name fallback does not apply to card payments when the account has no card descriptor', () => {
		useAccount.mockReturnValue( {
			data: {
				account: { settings: { payments: {}, card_payments: {} } },
				default_statement_descriptor: 'My Woo Store',
			},
		} );

		render( <PaymentsAndTransactionsSection /> );

		expect(
			screen.getByText( /Card payments do not use this default/ )
		).toBeInTheDocument();
	} );

	it( 'does not warn about card payments when the account has a card descriptor prefix', () => {
		useAccount.mockReturnValue( {
			data: {
				account: {
					settings: {
						payments: {},
						card_payments: {
							statement_descriptor_prefix: 'WOOTEST',
						},
					},
				},
				default_statement_descriptor: 'My Woo Store',
			},
		} );

		render( <PaymentsAndTransactionsSection /> );

		// The store name still fills non-card methods, but cards are covered by the prefix.
		expect(
			screen.getByText( /so your store name is used/ )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /Card payments do not use this default/ )
		).not.toBeInTheDocument();
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
