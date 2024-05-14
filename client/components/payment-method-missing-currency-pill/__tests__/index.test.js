import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodMissingCurrencyPill from '..';
import { useAliPayCurrencies } from 'utils/use-alipay-currencies';

jest.mock( '../../../payment-methods-map', () => ( {
	card: { currencies: [] },
	giropay: { currencies: [ 'EUR' ] },
} ) );

jest.mock( 'utils/use-alipay-currencies', () => ( {
	useAliPayCurrencies: jest.fn(),
} ) );

describe( 'PaymentMethodMissingCurrencyPill', () => {
	beforeEach( () => {
		global.wcSettings = { currency: { code: 'USD' } };
		useAliPayCurrencies.mockReturnValue( [ 'EUR', 'CNY' ] );
	} );

	it( 'should render the "Requires currency" text', () => {
		render(
			<PaymentMethodMissingCurrencyPill id="giropay" label="giropay" />
		);

		expect( screen.queryByText( 'Requires currency' ) ).toBeInTheDocument();
	} );

	it( 'should not render when currency matches', () => {
		global.wcSettings = { currency: { code: 'EUR' } };
		const { container } = render(
			<PaymentMethodMissingCurrencyPill id="giropay" label="giropay" />
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'should render when currency differs', () => {
		render(
			<PaymentMethodMissingCurrencyPill id="giropay" label="giropay" />
		);

		expect( screen.queryByText( 'Requires currency' ) ).toBeInTheDocument();
	} );
} );
