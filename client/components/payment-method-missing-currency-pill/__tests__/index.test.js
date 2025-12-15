import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodMissingCurrencyPill from '..';
import { usePaymentMethodCurrencies } from 'utils/use-payment-method-currencies';

jest.mock( '../../../payment-methods-map', () => ( {
	card: { currencies: [] },
	giropay: { currencies: [ 'EUR' ] },
} ) );

jest.mock( 'utils/use-payment-method-currencies', () => ( {
	usePaymentMethodCurrencies: jest.fn(),
} ) );

jest.mock( 'utils/use-payment-method-unavailable-reason' );

describe( 'PaymentMethodMissingCurrencyPill', () => {
	beforeEach( () => {
		usePaymentMethodCurrencies.mockReturnValue( [ 'EUR' ] );
	} );

	it( 'should render the "Requires currency" text when currency is not supported', () => {
		render(
			<PaymentMethodMissingCurrencyPill id="giropay" label="giropay" />
		);

		expect( screen.queryByText( 'Requires currency' ) ).toBeInTheDocument();
	} );
} );
