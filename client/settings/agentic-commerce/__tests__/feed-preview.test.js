import {
	render,
	screen,
	waitFor,
	fireEvent,
	within,
} from '@testing-library/react';
import AgenticCommerceFeedPreview from '../feed-preview';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch' );

const PREVIEW_PATH = '/wc/v3/wc_stripe/agentic-commerce/preview';

const PREVIEW_RESPONSE = {
	total_count: 5,
	included_count: 3,
	excluded_count: 1,
	excluded_breakdown: { subscriptions: 1, filtered: 0 },
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
		expect( screen.getByText( 'Excluded' ) ).toBeInTheDocument();
		expect( screen.getByText( '3' ) ).toBeInTheDocument(); // included count
	} );

	it( 'keeps the excluded breakdown collapsed until Details is opened', async () => {
		apiFetch.mockResolvedValue( PREVIEW_RESPONSE );

		render( <AgenticCommerceFeedPreview /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: /Preview feed/i } )
		);

		// "by filters" wording is gone; the breakdown hides behind a Details toggle.
		await waitFor( () => {
			expect( screen.getByText( 'Excluded' ) ).toBeInTheDocument();
		} );
		expect(
			screen.queryByText( 'Excluded by filters' )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText( /subscription product/i )
		).not.toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'button', { name: /Details/i } ) );

		expect(
			screen.getByText( /1 subscription product/i )
		).toBeInTheDocument();
	} );

	it( 'breaks the excluded count down by reason', async () => {
		apiFetch.mockResolvedValue( {
			...PREVIEW_RESPONSE,
			excluded_count: 14,
			excluded_breakdown: { subscriptions: 12, filtered: 2 },
		} );

		render( <AgenticCommerceFeedPreview /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: /Preview feed/i } )
		);

		await waitFor( () => {
			expect(
				screen.getByRole( 'button', { name: /Details/i } )
			).toBeInTheDocument();
		} );
		fireEvent.click( screen.getByRole( 'button', { name: /Details/i } ) );

		expect(
			screen.getByText( /12 subscription products/i )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				/2 products hidden by your product visibility settings/i
			)
		).toBeInTheDocument();
	} );

	it( 'omits a breakdown reason whose count is zero', async () => {
		apiFetch.mockResolvedValue( {
			...PREVIEW_RESPONSE,
			excluded_count: 12,
			excluded_breakdown: { subscriptions: 12, filtered: 0 },
		} );

		render( <AgenticCommerceFeedPreview /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: /Preview feed/i } )
		);

		await waitFor( () => {
			expect(
				screen.getByRole( 'button', { name: /Details/i } )
			).toBeInTheDocument();
		} );
		fireEvent.click( screen.getByRole( 'button', { name: /Details/i } ) );

		expect(
			screen.getByText( /12 subscription products/i )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /hidden by your product visibility settings/i )
		).not.toBeInTheDocument();
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

	it( 'warns that the preview is partial when the scan was capped', async () => {
		apiFetch.mockResolvedValue( {
			...PREVIEW_RESPONSE,
			total_count: 2000,
			scan_limited: true,
		} );

		const { container } = render( <AgenticCommerceFeedPreview /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: /Preview feed/i } )
		);

		// Scope to the component output: @wordpress/a11y appends a duplicate
		// speak region to document.body that lingers across tests.
		await waitFor( () => {
			expect(
				within( container ).getByText(
					/This preview covers the first/i
				)
			).toBeInTheDocument();
		} );
	} );

	it( 'does not warn about a partial preview when the scan completed', async () => {
		apiFetch.mockResolvedValue( {
			...PREVIEW_RESPONSE,
			scan_limited: false,
		} );

		const { container } = render( <AgenticCommerceFeedPreview /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: /Preview feed/i } )
		);

		await waitFor( () => {
			expect( screen.getByText( 'Included' ) ).toBeInTheDocument();
		} );
		expect(
			within( container ).queryByText( /This preview covers the first/i )
		).not.toBeInTheDocument();
	} );

	it( 'links the WooCommerce logs from the error notice', async () => {
		apiFetch.mockRejectedValue( { message: 'boom' } );

		render( <AgenticCommerceFeedPreview /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: /Preview feed/i } )
		);

		await waitFor( () => {
			expect(
				screen.getByText( 'WooCommerce logs' ).closest( 'a' )
			).toHaveAttribute(
				'href',
				'/wp-admin/admin.php?page=wc-status&tab=logs'
			);
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
