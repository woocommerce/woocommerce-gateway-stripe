import React from 'react';
import { render, screen } from '@testing-library/react';
import BalanceCard from '../balance-card';
import { useBalance } from 'wcstripe/data/payouts';

jest.mock( 'wcstripe/data/payouts', () => ( {
	useBalance: jest.fn(),
} ) );

describe( 'BalanceCard', () => {
	beforeEach( () => {
		global.wcSettings = { currency: { code: 'USD' } };
	} );

	it( 'renders a spinner while loading and no data is cached', () => {
		useBalance.mockReturnValue( {
			balance: null,
			isLoading: true,
			error: null,
			refresh: jest.fn(),
		} );

		const { container } = render( <BalanceCard /> );
		expect(
			container.querySelector( '.components-spinner' )
		).not.toBeNull();
	} );

	it( 'renders the heading and available/pending amounts', () => {
		useBalance.mockReturnValue( {
			balance: {
				available: [ { amount: 1500, currency: 'usd' } ],
				pending: [ { amount: 500, currency: 'usd' } ],
				instant_available: [],
			},
			isLoading: false,
			error: null,
			refresh: jest.fn(),
		} );

		render( <BalanceCard /> );

		expect( screen.getByText( 'Stripe balance' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Available' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Pending' ) ).toBeInTheDocument();
		// Our mock CurrencyFactory formats as $N.NN.
		expect( screen.getByText( '$15.00' ) ).toBeInTheDocument();
		expect( screen.getByText( '$5.00' ) ).toBeInTheDocument();
	} );

	it( 'renders an error notice when the error field is set', () => {
		useBalance.mockReturnValue( {
			balance: null,
			isLoading: false,
			error: 'Boom',
			refresh: jest.fn(),
		} );

		const { container } = render( <BalanceCard /> );
		expect(
			container.querySelector( '.components-notice.is-error' )
		).toHaveTextContent( 'Boom' );
	} );
} );
