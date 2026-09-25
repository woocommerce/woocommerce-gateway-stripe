import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import PayoutsTable from '../payouts-table';
import apiFetch from '@wordpress/api-fetch';
import { getQueryArg } from '@wordpress/url';

jest.mock( '@wordpress/api-fetch' );

// The real DataViews renders a page <select>; this stub exposes one button per
// page it would offer, so the tests can drive multi-page jumps directly.
jest.mock( '@wordpress/dataviews/wp', () => ( {
	DataViews: ( { data, view, onChangeView, isLoading, paginationInfo } ) => (
		<div>
			<p>{ isLoading ? 'loading' : `page ${ view.page }` }</p>
			<ul>
				{ data.map( ( item ) => (
					<li key={ item.id }>{ item.id }</li>
				) ) }
			</ul>
			{ Array.from(
				{ length: paginationInfo.totalPages },
				( _, index ) => index + 1
			).map( ( page ) => (
				<button
					key={ page }
					onClick={ () => onChangeView( { ...view, page } ) }
				>
					{ `Go to page ${ page }` }
				</button>
			) ) }
			<button onClick={ () => onChangeView( { ...view, perPage: 10 } ) }>
				Use 10 per page
			</button>
		</div>
	),
} ) );

const makePayouts = ( count ) =>
	Array.from( { length: count }, ( _, index ) => ( {
		id: `po_test${ index + 1 }`,
	} ) );

const mockPayoutsApi = ( payouts, { failCursors = [] } = {} ) => {
	apiFetch.mockImplementation( ( { path } ) => {
		const startingAfter = getQueryArg( path, 'starting_after' );

		if ( failCursors.includes( startingAfter ) ) {
			return Promise.reject( new Error( 'Request failed' ) );
		}

		const limit = Number( getQueryArg( path, 'limit' ) );
		const start = startingAfter
			? payouts.findIndex( ( { id } ) => id === startingAfter ) + 1
			: 0;

		return Promise.resolve( {
			data: payouts.slice( start, start + limit ),
			has_more: start + limit < payouts.length,
		} );
	} );
};

const lastStartingAfter = () =>
	getQueryArg(
		apiFetch.mock.calls[ apiFetch.mock.calls.length - 1 ][ 0 ].path,
		'starting_after'
	);

const goToPage = async ( page ) => {
	fireEvent.click(
		screen.getByRole( 'button', { name: `Go to page ${ page }` } )
	);
	await screen.findByText( `page ${ page }` );
};

describe( 'PayoutsTable pagination', () => {
	it( 'keeps cursors aligned to pages when revisiting pages out of order', async () => {
		mockPayoutsApi( makePayouts( 120 ) );
		render( <PayoutsTable /> );
		await screen.findByText( 'page 1' );

		await goToPage( 2 );
		await goToPage( 1 );
		await goToPage( 2 );
		await goToPage( 3 );

		expect( lastStartingAfter() ).toBe( 'po_test50' );
		expect( screen.getByText( 'po_test51' ) ).toBeInTheDocument();
	} );

	it( 'jumps back more than one page using the cursor for that page', async () => {
		mockPayoutsApi( makePayouts( 120 ) );
		render( <PayoutsTable /> );
		await screen.findByText( 'page 1' );

		await goToPage( 2 );
		await goToPage( 3 );
		await goToPage( 4 );
		await goToPage( 2 );

		expect( lastStartingAfter() ).toBe( 'po_test25' );
		expect( screen.getByText( 'po_test26' ) ).toBeInTheDocument();
	} );

	it( 'offers already-visited pages ahead of the current one', async () => {
		mockPayoutsApi( makePayouts( 120 ) );
		render( <PayoutsTable /> );
		await screen.findByText( 'page 1' );

		await goToPage( 2 );
		await goToPage( 3 );
		await goToPage( 4 );
		await goToPage( 2 );

		expect(
			screen.getByRole( 'button', { name: 'Go to page 5' } )
		).toBeInTheDocument();

		await goToPage( 4 );

		expect( lastStartingAfter() ).toBe( 'po_test75' );
		expect( screen.getByText( 'po_test76' ) ).toBeInTheDocument();
	} );

	it( 'does not record a cursor from rows left over after a failed load', async () => {
		mockPayoutsApi( makePayouts( 120 ), { failCursors: [ 'po_test25' ] } );
		render( <PayoutsTable /> );
		await screen.findByText( 'page 1' );

		await goToPage( 2 );

		expect( lastStartingAfter() ).toBe( 'po_test25' );
		// Page 1's rows stay on screen, but must not become page 3's cursor.
		expect( screen.getByText( 'po_test1' ) ).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Go to page 3' } )
		).not.toBeInTheDocument();
	} );

	it( 'stops offering pages once the list is exhausted', async () => {
		mockPayoutsApi( makePayouts( 30 ) );
		render( <PayoutsTable /> );
		await screen.findByText( 'page 1' );

		await goToPage( 2 );

		expect( screen.getByText( 'po_test30' ) ).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Go to page 3' } )
		).not.toBeInTheDocument();
	} );

	it( 'restarts from the first page when the page size changes', async () => {
		mockPayoutsApi( makePayouts( 120 ) );
		render( <PayoutsTable /> );
		await screen.findByText( 'page 1' );

		await goToPage( 2 );
		await goToPage( 3 );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Use 10 per page' } )
		);
		await screen.findByText( 'page 1' );

		expect( lastStartingAfter() ).toBeUndefined();
		expect( screen.getByText( 'po_test10' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'po_test11' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Go to page 3' } )
		).not.toBeInTheDocument();
	} );
} );
