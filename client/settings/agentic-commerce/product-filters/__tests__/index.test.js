import React, { createRef } from 'react';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import ProductFilters from '..';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch' );

const FILTERS_PATH = '/wc/v3/wc_stripe/agentic-commerce/filters';

const EMPTY_FILTERS = {
	product_ids: [],
	variable_product_ids: [],
	category_ids: [],
	tag_ids: [],
	brand_ids: [],
	products: [],
	variable_products: [],
	brand_taxonomy_available: false,
};

// Build a parse:false-style Response stub for the taxonomy term fetches.
const makeTermsResponse = ( terms ) => ( {
	json: () => Promise.resolve( terms ),
	headers: { get: () => '1' },
} );

/**
 * Route apiFetch by path/options. `filters` overrides the GET /filters body;
 * `terms` maps a taxonomy rest base to its term list.
 *
 * @param {Object} options
 * @param {Object} options.filters Filters GET response overrides.
 * @param {Object} options.terms   Map of rest base → term array.
 */
const mockApiFetch = ( { filters = {}, terms = {} } = {} ) => {
	const filtersResponse = { ...EMPTY_FILTERS, ...filters };

	apiFetch.mockImplementation( ( opts ) => {
		const { path, method } = opts;

		if ( method === 'POST' ) {
			return Promise.resolve( filtersResponse );
		}
		if ( path === FILTERS_PATH ) {
			return Promise.resolve( filtersResponse );
		}
		if ( path.startsWith( '/wc/v3/products/categories' ) ) {
			return Promise.resolve(
				makeTermsResponse( terms.categories ?? [] )
			);
		}
		if ( path.startsWith( '/wc/v3/products/tags' ) ) {
			return Promise.resolve( makeTermsResponse( terms.tags ?? [] ) );
		}
		if ( path.startsWith( '/wc/v3/products/brands' ) ) {
			return Promise.resolve( makeTermsResponse( terms.brands ?? [] ) );
		}
		// Product suggestion search.
		return Promise.resolve( [] );
	} );
};

describe( 'ProductFilters', () => {
	afterEach( () => {
		jest.resetAllMocks();
	} );

	it( 'defaults to the taxonomies mode and renders category/tag sections', async () => {
		mockApiFetch( {
			terms: {
				categories: [ { id: 1, name: 'Shoes' } ],
				tags: [ { id: 2, name: 'Sale' } ],
			},
		} );

		render( <ProductFilters /> );

		await waitFor( () => {
			expect( screen.getByLabelText( 'Shoes' ) ).toBeInTheDocument();
		} );
		expect( screen.getByText( 'Categories' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Tags' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Sale' ) ).toBeInTheDocument();
	} );

	it( 'hides the Brands section when the brand taxonomy is unavailable', async () => {
		mockApiFetch( { filters: { brand_taxonomy_available: false } } );

		render( <ProductFilters /> );

		await waitFor( () => {
			expect( screen.getByText( 'Categories' ) ).toBeInTheDocument();
		} );
		expect( screen.queryByText( 'Brands' ) ).not.toBeInTheDocument();
	} );

	it( 'shows the Brands section when the brand taxonomy is available', async () => {
		mockApiFetch( {
			filters: { brand_taxonomy_available: true },
			terms: { brands: [ { id: 9, name: 'Acme' } ] },
		} );

		render( <ProductFilters /> );

		await waitFor( () => {
			expect( screen.getByLabelText( 'Acme' ) ).toBeInTheDocument();
		} );
		expect( screen.getByText( 'Brands' ) ).toBeInTheDocument();
	} );

	it( 'derives the products mode from stored product_ids and renders the picker', async () => {
		mockApiFetch( {
			filters: {
				product_ids: [ 5 ],
				products: [ { id: 5, name: 'Boot' } ],
			},
		} );

		render( <ProductFilters /> );

		await waitFor( () => {
			expect( screen.getByText( 'Boot' ) ).toBeInTheDocument();
		} );
		// Taxonomy sections should not render in products mode.
		expect( screen.queryByText( 'Categories' ) ).not.toBeInTheDocument();
	} );

	it( 'save() posts the active taxonomy selection with other groups empty', async () => {
		mockApiFetch( {
			terms: { categories: [ { id: 1, name: 'Shoes' } ] },
		} );
		const ref = createRef();

		render( <ProductFilters ref={ ref } /> );

		await waitFor( () => {
			expect( screen.getByLabelText( 'Shoes' ) ).toBeInTheDocument();
		} );

		fireEvent.click( screen.getByLabelText( 'Shoes' ) );

		await ref.current.save();

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: FILTERS_PATH,
				method: 'POST',
				data: {
					product_ids: [],
					variable_product_ids: [],
					category_ids: [ 1 ],
					tag_ids: [],
					brand_ids: [],
				},
			} )
		);
	} );

	it( 'save() posts only product_ids when in the products mode', async () => {
		mockApiFetch( {
			filters: {
				product_ids: [ 5 ],
				products: [ { id: 5, name: 'Boot' } ],
			},
		} );
		const ref = createRef();

		render( <ProductFilters ref={ ref } /> );

		await waitFor( () => {
			expect( screen.getByText( 'Boot' ) ).toBeInTheDocument();
		} );

		await ref.current.save();

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: FILTERS_PATH,
				method: 'POST',
				data: {
					product_ids: [ 5 ],
					variable_product_ids: [],
					category_ids: [],
					tag_ids: [],
					brand_ids: [],
				},
			} )
		);
	} );

	it( 'switching mode via the radio swaps the visible controls', async () => {
		mockApiFetch( {
			terms: { categories: [ { id: 1, name: 'Shoes' } ] },
		} );

		render( <ProductFilters /> );

		await waitFor( () => {
			expect( screen.getByText( 'Categories' ) ).toBeInTheDocument();
		} );

		fireEvent.click( screen.getByLabelText( 'Specific products' ) );

		await waitFor( () => {
			expect(
				screen.queryByText( 'Categories' )
			).not.toBeInTheDocument();
		} );
	} );
} );
