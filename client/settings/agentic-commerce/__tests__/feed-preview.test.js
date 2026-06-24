import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import AgenticCommerceFeedPreview from '../feed-preview';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch' );

const PREVIEW_PATH = '/wc/v3/wc_stripe/agentic-commerce/preview';

const PREVIEW_RESPONSE = {
	total_count: 5,
	included_count: 3,
	excluded_count: 1,
	invalid_count: 1,
	truncated: 0,
	validation_errors: [
		{
			product_id: 42,
			product_name: 'Lonely Sock',
			edit_link:
				'http://example.org/wp-admin/post.php?post=42&action=edit',
			errors: [
				'Either google_product_category or product_category must be provided.',
			],
		},
	],
};

describe( 'AgenticCommerceFeedPreview', () => {
	afterEach( () => {
		jest.resetAllMocks();
	} );

	it( 'renders the Preview feed button and no summary before fetching', () => {
		render( <AgenticCommerceFeedPreview /> );

		expect(
			screen.getByRole( 'button', { name: /Preview feed/i } )
		).toBeInTheDocument();
		// apiFetch should not run until the merchant asks for a preview.
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'fetches the preview and renders the summary counts on click', async () => {
		apiFetch.mockResolvedValue( PREVIEW_RESPONSE );

		render( <AgenticCommerceFeedPreview /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: /Preview feed/i } )
		);

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( { path: PREVIEW_PATH } );
		} );

		expect( screen.getByText( 'Included' ) ).toBeInTheDocument();
		expect( screen.getByText( 'With errors' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Excluded by filters' ) ).toBeInTheDocument();
		expect( screen.getByText( '3' ) ).toBeInTheDocument(); // included count
	} );

	it( 'lists products with validation errors, linking to the edit screen', async () => {
		apiFetch.mockResolvedValue( PREVIEW_RESPONSE );

		render( <AgenticCommerceFeedPreview /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: /Preview feed/i } )
		);

		await waitFor( () => {
			expect( screen.getByText( 'Lonely Sock' ) ).toBeInTheDocument();
		} );

		const link = screen.getByText( 'Lonely Sock' ).closest( 'a' );
		expect( link ).toHaveAttribute(
			'href',
			'http://example.org/wp-admin/post.php?post=42&action=edit'
		);
		expect(
			screen.getByText( /product_category must be provided/i )
		).toBeInTheDocument();
	} );

	it( 'renders the product name as plain text when no edit link is available', async () => {
		apiFetch.mockResolvedValue( {
			...PREVIEW_RESPONSE,
			validation_errors: [
				{
					product_id: 7,
					product_name: 'No Link Product',
					edit_link: '',
					errors: [ 'Required field "price" is missing or empty.' ],
				},
			],
		} );

		render( <AgenticCommerceFeedPreview /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: /Preview feed/i } )
		);

		await waitFor( () => {
			expect( screen.getByText( 'No Link Product' ) ).toBeInTheDocument();
		} );
		expect(
			screen.getByText( 'No Link Product' ).closest( 'a' )
		).toBeNull();
	} );

	it( 'shows the all-clear message when there are no validation errors', async () => {
		apiFetch.mockResolvedValue( {
			total_count: 3,
			included_count: 3,
			excluded_count: 0,
			invalid_count: 0,
			truncated: 0,
			validation_errors: [],
		} );

		render( <AgenticCommerceFeedPreview /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: /Preview feed/i } )
		);

		await waitFor( () => {
			expect(
				screen.getByText(
					/All selected products are ready for agents/i
				)
			).toBeInTheDocument();
		} );
	} );

	it( 'shows a truncation note when more invalid products exist than were returned', async () => {
		apiFetch.mockResolvedValue( {
			...PREVIEW_RESPONSE,
			invalid_count: 53,
			truncated: 52,
		} );

		render( <AgenticCommerceFeedPreview /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: /Preview feed/i } )
		);

		await waitFor( () => {
			expect(
				screen.getByText( /and 52 more products with errors/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'shows an error notice when the preview request fails', async () => {
		apiFetch.mockRejectedValue( { message: 'boom' } );

		render( <AgenticCommerceFeedPreview /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: /Preview feed/i } )
		);

		await waitFor( () => {
			expect(
				screen.getAllByText( /Could not build the feed preview/i )
					.length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );
} );
