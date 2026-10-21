import React, { act } from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PaymentMethodMissingCurrencyPill from '..';
import { usePaymentMethodCurrencies } from 'utils/use-payment-method-currencies';

jest.mock( '../../../payment-methods-map', () => ( {
	card: { currencies: [] },
	bancontact: { currencies: [ 'EUR' ] },
} ) );

jest.mock( 'utils/use-payment-method-currencies', () => ( {
	usePaymentMethodCurrencies: jest.fn(),
} ) );

jest.mock( 'utils/use-payment-method-unavailable-reason' );

describe( 'PaymentMethodMissingCurrencyPill', () => {
	beforeEach( () => {
		usePaymentMethodCurrencies.mockReturnValue( [ 'EUR' ] );
	} );

	it( 'should render the currency requirement when currency is not supported', async () => {
		const { container } = render(
			<PaymentMethodMissingCurrencyPill
				id="bancontact"
				label="Bancontact"
			/>
		);

		expect( screen.queryByText( 'Requires currency' ) ).toBeInTheDocument();

		await act( async () => {
			await userEvent.click(
				container.querySelector( 'svg' ).parentElement
			);
		} );

		expect( document.body ).toHaveTextContent(
			'Bancontact requires store currency to be EUR. Set currency'
		);
	} );
} );
