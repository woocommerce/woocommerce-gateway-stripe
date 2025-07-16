import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodUnavailableDueConflictPill from '..';
import { usePaymentMethodCurrencies } from 'utils/use-payment-method-currencies';

jest.mock( '../../../payment-methods-map', () => ( {
	card: { currencies: [] },
	giropay: { currencies: [ 'EUR' ] },
} ) );

jest.mock( 'utils/use-payment-method-currencies', () => ( {
	usePaymentMethodCurrencies: jest.fn(),
} ) );

describe( 'PaymentMethodUnavailableDueConflictPill', () => {
	beforeEach( () => {
		global.wcSettings = { currency: { code: 'USD' } };
		usePaymentMethodCurrencies.mockReturnValue( [ 'EUR' ] );
	} );

	it( 'should render the "Requires currency" text', () => {
		render(
			<PaymentMethodUnavailableDueConflictPill
				id="giropay"
				label="giropay"
			/>
		);

		expect(
			screen.queryByText( 'Unavailable due conflict' )
		).toBeInTheDocument();
	} );

	it( 'should not render when currency matches', () => {
		global.wcSettings = { currency: { code: 'EUR' } };
		const { container } = render(
			<PaymentMethodUnavailableDueConflictPill
				id="giropay"
				label="giropay"
			/>
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'should render when currency differs', () => {
		render(
			<PaymentMethodUnavailableDueConflictPill
				id="giropay"
				label="giropay"
			/>
		);

		expect(
			screen.queryByText( 'Unavailable due conflict' )
		).toBeInTheDocument();
	} );
} );
