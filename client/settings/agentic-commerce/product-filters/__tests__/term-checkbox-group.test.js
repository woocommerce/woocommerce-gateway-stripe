import React from 'react';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import TermCheckboxGroup from '../term-checkbox-group';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch' );

// Build a parse:false-style Response stub the component reads via .json() and
// the X-WP-TotalPages header.
const makePage = ( terms, totalPages = 1 ) => ( {
	json: () => Promise.resolve( terms ),
	headers: { get: () => String( totalPages ) },
} );

const renderGroup = ( props = {} ) =>
	render(
		<TermCheckboxGroup
			title="Categories"
			restBase="categories"
			value={ [] }
			onChange={ jest.fn() }
			{ ...props }
		/>
	);

describe( 'TermCheckboxGroup', () => {
	afterEach( () => {
		jest.resetAllMocks();
	} );

	it( 'renders a checkbox per fetched term', async () => {
		apiFetch.mockResolvedValue(
			makePage( [
				{ id: 1, name: 'Shoes' },
				{ id: 2, name: 'Hats' },
			] )
		);

		renderGroup();

		await waitFor( () => {
			expect( screen.getByLabelText( 'Shoes' ) ).toBeInTheDocument();
		} );
		expect( screen.getByLabelText( 'Hats' ) ).toBeInTheDocument();
	} );

	it( 'reflects the selected value as checked', async () => {
		apiFetch.mockResolvedValue(
			makePage( [
				{ id: 1, name: 'Shoes' },
				{ id: 2, name: 'Hats' },
			] )
		);

		renderGroup( { value: [ 2 ] } );

		await waitFor( () => {
			expect( screen.getByLabelText( 'Hats' ) ).toBeChecked();
		} );
		expect( screen.getByLabelText( 'Shoes' ) ).not.toBeChecked();
	} );

	it( 'aggregates terms across all pages', async () => {
		apiFetch
			.mockResolvedValueOnce(
				makePage( [ { id: 1, name: 'Shoes' } ], 2 )
			)
			.mockResolvedValueOnce(
				makePage( [ { id: 2, name: 'Hats' } ], 2 )
			);

		renderGroup();

		await waitFor( () => {
			expect( screen.getByLabelText( 'Hats' ) ).toBeInTheDocument();
		} );
		expect( screen.getByLabelText( 'Shoes' ) ).toBeInTheDocument();
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
		expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toContain( 'page=1' );
		expect( apiFetch.mock.calls[ 1 ][ 0 ].path ).toContain( 'page=2' );
	} );

	it( 'adds a term ID on check', async () => {
		const onChange = jest.fn();
		apiFetch.mockResolvedValue( makePage( [ { id: 1, name: 'Shoes' } ] ) );

		renderGroup( { value: [], onChange } );

		await waitFor( () => {
			expect( screen.getByLabelText( 'Shoes' ) ).toBeInTheDocument();
		} );
		fireEvent.click( screen.getByLabelText( 'Shoes' ) );

		expect( onChange ).toHaveBeenCalledWith( [ 1 ] );
	} );

	it( 'removes a term ID on uncheck', async () => {
		const onChange = jest.fn();
		apiFetch.mockResolvedValue(
			makePage( [
				{ id: 1, name: 'Shoes' },
				{ id: 2, name: 'Hats' },
			] )
		);

		renderGroup( { value: [ 1, 2 ], onChange } );

		await waitFor( () => {
			expect( screen.getByLabelText( 'Shoes' ) ).toBeInTheDocument();
		} );
		fireEvent.click( screen.getByLabelText( 'Shoes' ) );

		expect( onChange ).toHaveBeenCalledWith( [ 2 ] );
	} );

	it( 'shows an empty message when there are no terms', async () => {
		apiFetch.mockResolvedValue( makePage( [] ) );

		renderGroup();

		await waitFor( () => {
			expect( screen.getByText( 'No items found.' ) ).toBeInTheDocument();
		} );
	} );

	it( 'shows an empty message when the fetch fails', async () => {
		apiFetch.mockRejectedValue( new Error( 'network' ) );

		renderGroup();

		await waitFor( () => {
			expect( screen.getByText( 'No items found.' ) ).toBeInTheDocument();
		} );
	} );
} );
