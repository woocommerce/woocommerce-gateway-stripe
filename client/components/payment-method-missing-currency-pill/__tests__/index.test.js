import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodMissingCurrencyPill from '..';
import { usePaymentMethodCurrencies } from 'utils/use-payment-method-currencies';
import usePaymentMethodUnavailableReason from 'utils/use-payment-method-unavailable-reason';
import { PAYMENT_METHOD_UNAVAILABLE_REASONS } from 'wcstripe/stripe-utils/constants';

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
		global.wcSettings = { currency: { code: 'USD' } };
		usePaymentMethodCurrencies.mockReturnValue( [ 'EUR' ] );
	} );

	it( 'should render the "Requires currency" text when currency is not supported', () => {
		usePaymentMethodUnavailableReason.mockReturnValue(
			PAYMENT_METHOD_UNAVAILABLE_REASONS.UNSUPPORTED_CURRENCY
		);

		render(
			<PaymentMethodMissingCurrencyPill id="giropay" label="giropay" />
		);

		expect( screen.queryByText( 'Requires currency' ) ).toBeInTheDocument();
	} );

	it( 'should not render when currency is supported', () => {
		usePaymentMethodUnavailableReason.mockReturnValue( null );

		const { container } = render(
			<PaymentMethodMissingCurrencyPill id="giropay" label="giropay" />
		);

		expect( container.firstChild ).toBeNull();
	} );
} );
