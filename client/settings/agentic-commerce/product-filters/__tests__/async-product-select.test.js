import React from 'react';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import AsyncProductSelect from '../async-product-select';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch' );

const renderSelect = ( props = {} ) =>
	render(
		<AsyncProductSelect
			label="Products"
			productType="simple"
			value={ [] }
			initialLabels={ [] }
			onChange={ jest.fn() }
			{ ...props }
		/>
	);

describe( 'AsyncProductSelect', () => {
	afterEach( () => {
		jest.resetAllMocks();
	} );

	it( 'renders existing selections as named tokens from initialLabels', () => {
		renderSelect( {
			value: [ 5 ],
			initialLabels: [ { id: 5, name: 'Boot' } ],
		} );

		expect( screen.getByText( 'Boot' ) ).toBeInTheDocument();
	} );

	it( 'drops selected IDs that have no resolved label', () => {
		renderSelect( {
			value: [ 5, 9 ],
			initialLabels: [ { id: 5, name: 'Boot' } ],
		} );

		// 5 resolves to "Boot"; 9 has no label and must not render a token.
		expect( screen.getByText( 'Boot' ) ).toBeInTheDocument();
		expect( screen.queryByText( '9' ) ).not.toBeInTheDocument();
	} );

	it( 'debounces a product search to wc/v3/products with the type and search params', async () => {
		apiFetch.mockResolvedValue( [ { id: 7, name: 'Sandal' } ] );

		renderSelect( { productType: 'variable' } );

		const input = screen.getByRole( 'combobox' );
		fireEvent.change( input, { target: { value: 'san' } } );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		} );
		const calledPath = apiFetch.mock.calls[ 0 ][ 0 ].path;
		expect( calledPath ).toContain( '/wc/v3/products?' );
		expect( calledPath ).toContain( 'type=variable' );
		expect( calledPath ).toContain( 'search=san' );
	} );

	it( 'coalesces rapid keystrokes into a single debounced request', async () => {
		apiFetch.mockResolvedValue( [] );

		renderSelect();

		const input = screen.getByRole( 'combobox' );
		fireEvent.change( input, { target: { value: 'a' } } );
		fireEvent.change( input, { target: { value: 'ab' } } );
		fireEvent.change( input, { target: { value: 'abc' } } );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		} );
		expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toContain( 'search=abc' );
	} );

	it( 'survives a failed search without crashing', async () => {
		apiFetch.mockRejectedValue( new Error( 'network' ) );

		renderSelect( {
			value: [ 5 ],
			initialLabels: [ { id: 5, name: 'Boot' } ],
		} );

		const input = screen.getByRole( 'combobox' );
		fireEvent.change( input, { target: { value: 'x' } } );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalled();
		} );
		// The existing selection is still rendered; the failure was swallowed.
		expect( screen.getByText( 'Boot' ) ).toBeInTheDocument();
	} );
} );
