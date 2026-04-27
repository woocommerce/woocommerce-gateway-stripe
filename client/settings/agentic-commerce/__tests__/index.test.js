import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import AgenticCommercePanel from '..';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch' );

// Static baseline response. next_sync is intentionally omitted here so
// individual tests can provide a value that is always relative to real time.
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

const makeResponse = ( overrides = {} ) => ( {
	last_sync: LAST_SYNC_SUCCESS,
	history: HISTORY,
	next_sync: null,
	...overrides,
} );

const EMPTY_RESPONSE = { last_sync: null, history: [], next_sync: null };

describe( 'AgenticCommercePanel', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = {
			agentic_commerce_import_sets_url:
				'https://dashboard.stripe.com/test/data-management/import-sets',
			agentic_commerce_logs_url:
				'/wp-admin/admin.php?page=wc-status&tab=logs&source=woocommerce-gateway-stripe',
		};
	} );

	afterEach( () => {
		delete global.wc_stripe_settings_params;
		jest.resetAllMocks();
	} );

	// -------------------------------------------------------------------------
	// Loading state
	// -------------------------------------------------------------------------

	it( 'shows loading indicators while fetching', () => {
		apiFetch.mockReturnValueOnce( new Promise( () => {} ) );

		render( <AgenticCommercePanel /> );

		expect(
			screen.getAllByText( /Loading…/i ).length
		).toBeGreaterThanOrEqual( 1 );
	} );

	// -------------------------------------------------------------------------
	// Empty state
	// -------------------------------------------------------------------------

	it( 'shows "No syncs yet" when last_sync is null', async () => {
		apiFetch.mockResolvedValueOnce( EMPTY_RESPONSE );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( screen.getByText( /No syncs yet/i ) ).toBeInTheDocument();
		} );
	} );

	it( 'shows "No sync history available" when history is empty', async () => {
		apiFetch.mockResolvedValueOnce( EMPTY_RESPONSE );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByText( /No sync history available/i )
			).toBeInTheDocument();
		} );
	} );

	// -------------------------------------------------------------------------
	// Populated state — last_sync card
	// -------------------------------------------------------------------------

	it( 'renders a success status badge when last sync succeeded', async () => {
		apiFetch.mockResolvedValueOnce( makeResponse() );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			// At least one "Success" badge must be in the document (may also
			// appear in the history table row and the aria-live region).
			expect(
				screen.getAllByText( /Success/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	it( 'renders product count from last_sync', async () => {
		apiFetch.mockResolvedValueOnce( makeResponse() );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			// "42" appears in the status table (products synced).
			expect( screen.getAllByText( '42' ).length ).toBeGreaterThanOrEqual(
				1
			);
		} );
	} );

	it( 'renders import_set_id from last_sync', async () => {
		apiFetch.mockResolvedValueOnce( makeResponse() );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getAllByText( 'impset_abc' ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	// -------------------------------------------------------------------------
	// Populated state — history table
	// -------------------------------------------------------------------------

	it( 'renders the history table with correct row count', async () => {
		apiFetch.mockResolvedValueOnce( makeResponse() );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( screen.getByText( 'impset_prev' ) ).toBeInTheDocument();
		} );
	} );

	it( 'shows an info icon next to failed history rows that have an error', async () => {
		apiFetch.mockResolvedValueOnce( makeResponse() );

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
		apiFetch.mockResolvedValueOnce(
			makeResponse( {
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
		apiFetch.mockResolvedValueOnce(
			makeResponse( { next_sync: futureTs } )
		);

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByText( /Next automatic sync: in (29|30) minutes?/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'shows "imminent" label when next_sync is just in the past', async () => {
		const pastTs = Math.floor( Date.now() / 1000 ) - 100;
		apiFetch.mockResolvedValueOnce( makeResponse( { next_sync: pastTs } ) );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( screen.getByText( /imminent/i ) ).toBeInTheDocument();
		} );
	} );

	// Excludes the hidden a11y-speak-region (which @wordpress/components Notice
	// populates as a side effect and persists across renders) so assertions
	// only inspect what's actually rendered by the panel.
	const getVisibleText = ( pattern ) =>
		screen
			.queryAllByText( pattern )
			.filter( ( el ) => ! el.closest( '.a11y-speak-region' ) );

	it( 'shows an overdue warning when next_sync is far in the past', async () => {
		// 30 minutes overdue — well past the 10-minute threshold.
		const overdueTs = Math.floor( Date.now() / 1000 ) - 30 * 60;
		apiFetch.mockResolvedValueOnce(
			makeResponse( { next_sync: overdueTs } )
		);

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				getVisibleText(
					/scheduled sync is overdue by 3(0|1) minutes?/i
				).length
			).toBeGreaterThanOrEqual( 1 );
		} );

		expect(
			getVisibleText( /Action Scheduler is running/i ).length
		).toBeGreaterThanOrEqual( 1 );
	} );

	it( 'does not show an overdue warning when next_sync is only slightly in the past', async () => {
		// 2 minutes in the past — within the "imminent" window.
		const pastTs = Math.floor( Date.now() / 1000 ) - 2 * 60;
		apiFetch.mockResolvedValueOnce( makeResponse( { next_sync: pastTs } ) );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( screen.getByText( /imminent/i ) ).toBeInTheDocument();
		} );

		expect( getVisibleText( /scheduled sync is overdue/i ) ).toHaveLength(
			0
		);
	} );

	// -------------------------------------------------------------------------
	// API fetch call
	// -------------------------------------------------------------------------

	it( 'fetches status from the correct REST path on mount', async () => {
		apiFetch.mockResolvedValueOnce( EMPTY_RESPONSE );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/wc_stripe/agentic-commerce/status',
			} );
		} );
	} );

	// -------------------------------------------------------------------------
	// Sync Now button
	// -------------------------------------------------------------------------

	it( 'renders the Sync Now button', async () => {
		apiFetch.mockResolvedValueOnce( EMPTY_RESPONSE );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByRole( 'button', { name: /Sync Now/i } )
			).toBeInTheDocument();
		} );
	} );

	it( 'shows success notice and re-fetches after a successful sync', async () => {
		apiFetch
			.mockResolvedValueOnce( EMPTY_RESPONSE )
			.mockResolvedValueOnce( { success: true } )
			.mockResolvedValueOnce( makeResponse() );

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
			.mockResolvedValueOnce( EMPTY_RESPONSE )
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
			.mockResolvedValueOnce( EMPTY_RESPONSE )
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
	// Fetch error
	// -------------------------------------------------------------------------

	it( 'shows an error notice when the initial fetch fails', async () => {
		apiFetch.mockRejectedValueOnce( { message: 'Connection refused' } );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getAllByText( /Connection refused/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );

		// Empty-state placeholders should NOT be shown when fetch fails.
		expect( screen.queryByText( /No syncs yet/i ) ).not.toBeInTheDocument();
		expect(
			screen.queryByText( /No sync history available/i )
		).not.toBeInTheDocument();
	} );

	// -------------------------------------------------------------------------
	// Auto-refresh while sync is in progress
	// -------------------------------------------------------------------------

	describe( 'auto-refresh while sync is in progress', () => {
		// Use a tiny poll interval so the test can wait on real timers without
		// pulling in fake-timer plumbing. The component accepts pollIntervalMs
		// as a prop precisely to keep this lightweight.
		const TEST_POLL_INTERVAL_MS = 25;

		it( 'polls /status while last sync is in a non-terminal state and stops once terminal', async () => {
			const inProgress = makeResponse( {
				last_sync: {
					...LAST_SYNC_SUCCESS,
					status: 'creating_records',
				},
			} );
			const terminal = makeResponse();

			apiFetch
				.mockResolvedValueOnce( inProgress ) // initial mount
				.mockResolvedValueOnce( inProgress ) // poll 1
				.mockResolvedValueOnce( terminal ) // poll 2 → terminal
				.mockResolvedValue( terminal ); // any extra polls

			render(
				<AgenticCommercePanel
					pollIntervalMs={ TEST_POLL_INTERVAL_MS }
				/>
			);

			// Wait until the dashboard has actually polled at least once
			// past its initial mount fetch — proves polling is active.
			await waitFor( () => {
				expect( apiFetch.mock.calls.length ).toBeGreaterThanOrEqual(
					2
				);
			} );

			// Wait until the terminal response has been consumed (third call).
			await waitFor( () => {
				expect( apiFetch.mock.calls.length ).toBeGreaterThanOrEqual(
					3
				);
			} );

			// After the terminal response, polling should stop. Capture the
			// current count and confirm it doesn't grow over several intervals.
			const callsAfterTerminal = apiFetch.mock.calls.length;
			await new Promise( ( resolve ) =>
				setTimeout( resolve, TEST_POLL_INTERVAL_MS * 6 )
			);
			expect( apiFetch ).toHaveBeenCalledTimes( callsAfterTerminal );
		} );

		it( 'does not poll when last sync is already in a terminal state', async () => {
			apiFetch.mockResolvedValueOnce( makeResponse() );

			render(
				<AgenticCommercePanel
					pollIntervalMs={ TEST_POLL_INTERVAL_MS }
				/>
			);

			// Wait for the initial fetch to settle. We can't simply
			// look for "Success" text because @wordpress/components Notice
			// retains text from prior tests in its a11y-speak region.
			await waitFor( () => {
				expect( apiFetch ).toHaveBeenCalledTimes( 1 );
			} );

			// Wait several polling intervals — terminal state must not poll.
			await new Promise( ( resolve ) =>
				setTimeout( resolve, TEST_POLL_INTERVAL_MS * 6 )
			);
			expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
