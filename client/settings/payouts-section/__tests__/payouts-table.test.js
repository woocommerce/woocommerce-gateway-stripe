import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PayoutsTable from '../payouts-table';
import { usePayouts } from 'wcstripe/data/payouts';

jest.mock( 'wcstripe/data/payouts', () => ( {
	usePayouts: jest.fn(),
} ) );

jest.mock( '@wordpress/dataviews/wp', () => ( {
	DataViews: ( { data, fields, getItemId } ) => (
		<div data-testid="dataviews">
			{ data.map( ( item ) => (
				<div key={ getItemId( item ) } data-testid="dataviews-row">
					{ fields.map( ( field ) => {
						const content = field.render
							? field.render( { item } )
							: item[ field.id ];
						return (
							<span
								key={ field.id }
								data-testid={ `cell-${ field.id }` }
							>
								{ content }
							</span>
						);
					} ) }
				</div>
			) ) }
		</div>
	),
} ) );

jest.mock( '@wordpress/date', () => ( {
	dateI18n: jest.fn( ( format, ts ) => `date(${ ts })` ),
} ) );

const baseHookReturn = {
	payouts: [],
	hasMore: false,
	isLoading: false,
	error: null,
	refresh: jest.fn(),
};

describe( 'PayoutsTable', () => {
	beforeEach( () => {
		global.wcSettings = { currency: { code: 'USD' } };
		usePayouts.mockReturnValue( baseHookReturn );
	} );

	it( 'renders the header', () => {
		render( <PayoutsTable /> );
		expect( screen.getByText( 'Payouts' ) ).toBeInTheDocument();
	} );

	it( 'renders a row per payout', () => {
		usePayouts.mockReturnValue( {
			...baseHookReturn,
			payouts: [
				{
					id: 'po_1',
					amount: 2500,
					currency: 'usd',
					status: 'paid',
					arrival_date: 1700000000,
					method: 'standard',
					description: 'STRIPE PAYOUT',
				},
				{
					id: 'po_2',
					amount: 1000,
					currency: 'usd',
					status: 'pending',
					arrival_date: 1700000100,
					method: 'instant',
					description: '',
				},
			],
		} );

		render( <PayoutsTable /> );
		expect( screen.getAllByTestId( 'dataviews-row' ) ).toHaveLength( 2 );
		expect( screen.getByText( 'STRIPE PAYOUT' ) ).toBeInTheDocument();
		// Falls back to id when description is empty.
		expect( screen.getByText( 'po_2' ) ).toBeInTheDocument();
	} );

	it( 'disables Next when has_more is false', () => {
		render( <PayoutsTable /> );
		expect( screen.getByRole( 'button', { name: /Next/ } ) ).toBeDisabled();
	} );

	it( 'enables Next when has_more is true', () => {
		usePayouts.mockReturnValue( {
			...baseHookReturn,
			payouts: [
				{ id: 'po_1', currency: 'usd', amount: 1, status: 'paid' },
			],
			hasMore: true,
		} );

		render( <PayoutsTable /> );
		expect( screen.getByRole( 'button', { name: /Next/ } ) ).toBeEnabled();
	} );

	it( 'disables Previous on the first page', () => {
		render( <PayoutsTable /> );
		expect(
			screen.getByRole( 'button', { name: /Previous/ } )
		).toBeDisabled();
	} );

	it( 'advances the cursor when Next is clicked', () => {
		usePayouts.mockReturnValue( {
			...baseHookReturn,
			payouts: [
				{ id: 'po_1', currency: 'usd', amount: 1, status: 'paid' },
				{ id: 'po_2', currency: 'usd', amount: 1, status: 'paid' },
			],
			hasMore: true,
		} );

		render( <PayoutsTable /> );
		userEvent.click( screen.getByRole( 'button', { name: /Next/ } ) );

		const lastCall =
			usePayouts.mock.calls[ usePayouts.mock.calls.length - 1 ];
		expect( lastCall[ 0 ].startingAfter ).toBe( 'po_2' );
	} );
} );
