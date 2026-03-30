import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import AgenticCommercePanel from '..';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch' );

// ---------------------------------------------------------------------------
// Feed status fixtures
// ---------------------------------------------------------------------------

const LAST_SYNC_SUCCESS = {
	status: 'succeeded',
	timestamp: 1700000000,
	products: 42,
	import_set_id: 'impset_abc',
	file_id: 'file_xyz',
	error: null,
};

const HISTORY = [
	{
		status: 'succeeded',
		timestamp: 1700000000,
		products: 42,
		import_set_id: 'impset_abc',
		error: null,
	},
	{
		status: 'failed',
		timestamp: 1699900000,
		products: 0,
		import_set_id: 'impset_prev',
		error: 'Network error',
	},
];

const makeFeedResponse = ( overrides = {} ) => ( {
	last_sync: LAST_SYNC_SUCCESS,
	history: HISTORY,
	next_sync: null,
	...overrides,
} );

const EMPTY_FEED_RESPONSE = { last_sync: null, history: [], next_sync: null };

// ---------------------------------------------------------------------------
// Merchant settings fixtures
// ---------------------------------------------------------------------------

const EMPTY_MERCHANT_SETTINGS = {
	terms_of_service_url: '',
	privacy_policy_url: '',
	refund_policy_url: '',
	hook_url: '',
	hook_secret: '',
	hook_secret_is_set: false,
};

const POPULATED_MERCHANT_SETTINGS = {
	terms_of_service_url: 'https://example.com/terms',
	privacy_policy_url: 'https://example.com/privacy',
	refund_policy_url: 'https://example.com/returns',
	hook_url: 'https://example.com/hook',
	hook_secret: '••••••••',
	hook_secret_is_set: true,
};

// ---------------------------------------------------------------------------
// Helper: mock both API calls that fire on mount (merchant-settings + feed status)
// ---------------------------------------------------------------------------

const mockBothOnMount = ( merchantOverrides = {}, feedOverrides = {} ) => {
	apiFetch
		.mockResolvedValueOnce( {
			...EMPTY_MERCHANT_SETTINGS,
			...merchantOverrides,
		} )
		.mockResolvedValueOnce( { ...EMPTY_FEED_RESPONSE, ...feedOverrides } );
};

describe( 'AgenticCommercePanel', () => {
	afterEach( () => {
		jest.resetAllMocks();
	} );

	// -------------------------------------------------------------------------
	// Loading state
	// -------------------------------------------------------------------------

	it( 'shows loading indicators while fetching', () => {
		// Both fetch calls never resolve during this test.
		apiFetch.mockReturnValue( new Promise( () => {} ) );

		render( <AgenticCommercePanel /> );

		expect(
			screen.getAllByText( /Loading…/i ).length
		).toBeGreaterThanOrEqual( 1 );
	} );

	// -------------------------------------------------------------------------
	// Onboarding panel — empty state
	// -------------------------------------------------------------------------

	it( 'renders the Agentic Commerce Setup card', async () => {
		mockBothOnMount();

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByText( /Agentic Commerce Setup/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'renders all four URL fields', async () => {
		mockBothOnMount();

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByLabelText( /Terms & Conditions URL/i )
			).toBeInTheDocument();
		} );
		await waitFor( () => {
			expect(
				screen.getByLabelText( /Privacy Policy URL/i )
			).toBeInTheDocument();
		} );
		await waitFor( () => {
			expect(
				screen.getByLabelText( /Refund & Return Policy URL/i )
			).toBeInTheDocument();
		} );
		await waitFor( () => {
			expect(
				screen.getByLabelText( /Customization Webhook URL/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'renders the Webhook Secret field', async () => {
		mockBothOnMount();

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByLabelText( /Webhook Secret/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'renders the Manage on Stripe Dashboard link', async () => {
		mockBothOnMount();

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByText( /Manage on Stripe Dashboard/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'renders the Save Settings button', async () => {
		mockBothOnMount();

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByRole( 'button', { name: /Save Settings/i } )
			).toBeInTheDocument();
		} );
	} );

	// -------------------------------------------------------------------------
	// Onboarding panel — populated state
	// -------------------------------------------------------------------------

	it( 'pre-fills URL fields from fetched settings', async () => {
		mockBothOnMount( POPULATED_MERCHANT_SETTINGS );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByDisplayValue( 'https://example.com/terms' )
			).toBeInTheDocument();
		} );
		await waitFor( () => {
			expect(
				screen.getByDisplayValue( 'https://example.com/privacy' )
			).toBeInTheDocument();
		} );
		await waitFor( () => {
			expect(
				screen.getByDisplayValue( 'https://example.com/returns' )
			).toBeInTheDocument();
		} );
		await waitFor( () => {
			expect(
				screen.getByDisplayValue( 'https://example.com/hook' )
			).toBeInTheDocument();
		} );
	} );

	it( 'shows "A webhook secret is already configured" when hook_secret_is_set is true', async () => {
		mockBothOnMount( POPULATED_MERCHANT_SETTINGS );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getAllByText( /A webhook secret is already configured/i )
					.length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	// -------------------------------------------------------------------------
	// Onboarding panel — save settings
	// -------------------------------------------------------------------------

	it( 'calls the merchant-settings POST endpoint on save', async () => {
		mockBothOnMount();

		render( <AgenticCommercePanel /> );

		const saveBtn = await screen.findByRole( 'button', {
			name: /Save Settings/i,
		} );

		// Set up the POST mock before clicking.
		apiFetch.mockResolvedValueOnce( EMPTY_MERCHANT_SETTINGS );

		fireEvent.click( saveBtn );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/wc/v3/wc_stripe/agentic-commerce/merchant-settings',
					method: 'POST',
				} )
			);
		} );
	} );

	it( 'shows success notice after saving', async () => {
		mockBothOnMount();

		render( <AgenticCommercePanel /> );

		const saveBtn = await screen.findByRole( 'button', {
			name: /Save Settings/i,
		} );

		apiFetch.mockResolvedValueOnce( EMPTY_MERCHANT_SETTINGS );
		fireEvent.click( saveBtn );

		await waitFor( () => {
			expect(
				screen.getAllByText( /Settings saved/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	it( 'shows error notice when save fails', async () => {
		mockBothOnMount();

		render( <AgenticCommercePanel /> );

		const saveBtn = await screen.findByRole( 'button', {
			name: /Save Settings/i,
		} );

		apiFetch.mockRejectedValueOnce( { message: 'Validation failed' } );
		fireEvent.click( saveBtn );

		await waitFor( () => {
			expect(
				screen.getAllByText( /Validation failed/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	it( 'shows fallback error message when save fails without a message', async () => {
		mockBothOnMount();

		render( <AgenticCommercePanel /> );

		const saveBtn = await screen.findByRole( 'button', {
			name: /Save Settings/i,
		} );

		apiFetch.mockRejectedValueOnce( {} );
		fireEvent.click( saveBtn );

		await waitFor( () => {
			expect(
				screen.getAllByText( /Failed to save settings/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	it( 'fetches merchant-settings from the correct REST path on mount', async () => {
		mockBothOnMount();

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/wc_stripe/agentic-commerce/merchant-settings',
			} );
		} );
	} );

	// -------------------------------------------------------------------------
	// Feed status — empty state
	// -------------------------------------------------------------------------

	it( 'shows "No syncs yet" when last_sync is null', async () => {
		mockBothOnMount();

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( screen.getByText( /No syncs yet/i ) ).toBeInTheDocument();
		} );
	} );

	it( 'shows "No sync history available" when history is empty', async () => {
		mockBothOnMount();

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByText( /No sync history available/i )
			).toBeInTheDocument();
		} );
	} );

	// -------------------------------------------------------------------------
	// Feed status — populated state
	// -------------------------------------------------------------------------

	it( 'renders a success status badge when last sync succeeded', async () => {
		mockBothOnMount( {}, makeFeedResponse() );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getAllByText( /Success/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	it( 'renders product count from last_sync', async () => {
		mockBothOnMount( {}, makeFeedResponse() );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( screen.getAllByText( '42' ).length ).toBeGreaterThanOrEqual(
				1
			);
		} );
	} );

	it( 'renders import_set_id from last_sync', async () => {
		mockBothOnMount( {}, makeFeedResponse() );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getAllByText( 'impset_abc' ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	// -------------------------------------------------------------------------
	// Feed status — history table
	// -------------------------------------------------------------------------

	it( 'renders the history table with correct row count', async () => {
		mockBothOnMount( {}, makeFeedResponse() );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( screen.getByText( 'impset_prev' ) ).toBeInTheDocument();
		} );
	} );

	it( 'shows an info icon next to failed history rows that have an error', async () => {
		mockBothOnMount( {}, makeFeedResponse() );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			const infoIcons = document.querySelectorAll(
				'[title="Network error"]'
			);
			expect( infoIcons.length ).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	// -------------------------------------------------------------------------
	// Error notice on last_sync
	// -------------------------------------------------------------------------

	it( 'renders the last sync error notice when last_sync has an error', async () => {
		mockBothOnMount(
			{},
			makeFeedResponse( {
				last_sync: {
					...LAST_SYNC_SUCCESS,
					status: 'failed',
					error: 'Stripe API key not configured',
				},
			} )
		);

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getAllByText( /Stripe API key not configured/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	// -------------------------------------------------------------------------
	// Next sync label — use real Date.now so timestamps are always relative
	// -------------------------------------------------------------------------

	it( 'shows next sync countdown when next_sync is in the future', async () => {
		const futureTs = Math.floor( Date.now() / 1000 ) + 1800; // 30 min ahead
		mockBothOnMount( {}, makeFeedResponse( { next_sync: futureTs } ) );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByText( /Next automatic sync: in \d+ minutes?/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'shows "imminent" label when next_sync is in the past', async () => {
		const pastTs = Math.floor( Date.now() / 1000 ) - 100;
		mockBothOnMount( {}, makeFeedResponse( { next_sync: pastTs } ) );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( screen.getByText( /imminent/i ) ).toBeInTheDocument();
		} );
	} );

	// -------------------------------------------------------------------------
	// API fetch calls
	// -------------------------------------------------------------------------

	it( 'fetches status from the correct REST path on mount', async () => {
		mockBothOnMount();

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/wc_stripe/agentic-commerce',
			} );
		} );
	} );

	// -------------------------------------------------------------------------
	// Sync Now button
	// -------------------------------------------------------------------------

	it( 'renders the Sync Now button', async () => {
		mockBothOnMount();

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByRole( 'button', { name: /Sync Now/i } )
			).toBeInTheDocument();
		} );
	} );

	it( 'shows success notice and re-fetches after a successful sync', async () => {
		apiFetch
			.mockResolvedValueOnce( EMPTY_MERCHANT_SETTINGS ) // merchant-settings GET
			.mockResolvedValueOnce( EMPTY_FEED_RESPONSE ) // feed status GET
			.mockResolvedValueOnce( { success: true } ) // sync POST
			.mockResolvedValueOnce( makeFeedResponse() ); // re-fetch after sync

		render( <AgenticCommercePanel /> );

		const syncBtn = await screen.findByRole( 'button', {
			name: /Sync Now/i,
		} );
		fireEvent.click( syncBtn );

		await waitFor( () => {
			expect(
				screen.getAllByText( /Sync triggered successfully/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );

		// After re-fetch, products count should appear.
		await waitFor( () => {
			expect( screen.getAllByText( '42' ).length ).toBeGreaterThanOrEqual(
				1
			);
		} );

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/wc/v3/wc_stripe/agentic-commerce/sync',
				method: 'POST',
			} )
		);
	} );

	it( 'shows error notice when sync POST fails', async () => {
		apiFetch
			.mockResolvedValueOnce( EMPTY_MERCHANT_SETTINGS )
			.mockResolvedValueOnce( EMPTY_FEED_RESPONSE )
			.mockRejectedValueOnce( { message: 'Server error' } );

		render( <AgenticCommercePanel /> );

		const syncBtn = await screen.findByRole( 'button', {
			name: /Sync Now/i,
		} );
		fireEvent.click( syncBtn );

		await waitFor( () => {
			expect(
				screen.getAllByText( /Server error/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	it( 'shows fallback error message when sync POST fails without a message', async () => {
		apiFetch
			.mockResolvedValueOnce( EMPTY_MERCHANT_SETTINGS )
			.mockResolvedValueOnce( EMPTY_FEED_RESPONSE )
			.mockRejectedValueOnce( {} );

		render( <AgenticCommercePanel /> );

		const syncBtn = await screen.findByRole( 'button', {
			name: /Sync Now/i,
		} );
		fireEvent.click( syncBtn );

		await waitFor( () => {
			expect(
				screen.getAllByText(
					/Sync failed. Check the WooCommerce logs/i
				).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	// -------------------------------------------------------------------------
	// Fetch error (initial load)
	// -------------------------------------------------------------------------

	it( 'shows an error notice when the initial merchant-settings fetch fails', async () => {
		apiFetch.mockRejectedValueOnce( { message: 'Connection refused' } );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getAllByText( /Connection refused/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );
} );
