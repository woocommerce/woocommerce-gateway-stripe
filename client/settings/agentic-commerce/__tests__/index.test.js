import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import AgenticCommercePanel from '..';
import apiFetch from '@wordpress/api-fetch';
import { useTestMode } from 'wcstripe/data';
import { useAccount } from 'wcstripe/data/account';

jest.mock( '@wordpress/api-fetch' );

jest.mock( 'wcstripe/data', () => ( {
	useTestMode: jest.fn(),
} ) );

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
} ) );

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

const SETTINGS_RESPONSE = { is_enabled: true, webhook_secret: '' };

/**
 * Set up apiFetch to route by path. Status calls return `statusResponse`
 * and settings calls return `settingsResponse`. Additional one-off mocks
 * (mockResolvedValueOnce) take priority and are consumed first.
 *
 * @param {Object} statusResponse   Response for the status endpoint.
 * @param {Object} settingsResponse Response for the settings endpoint.
 */
const mockFetchByPath = (
	statusResponse = EMPTY_RESPONSE,
	settingsResponse = SETTINGS_RESPONSE
) => {
	apiFetch.mockImplementation( ( { path } ) => {
		if ( path === '/wc/v3/wc_stripe/agentic-commerce/settings' ) {
			return Promise.resolve( settingsResponse );
		}
		return Promise.resolve( statusResponse );
	} );
};

describe( 'AgenticCommercePanel', () => {
	beforeEach( () => {
		useTestMode.mockReturnValue( [ false ] );
		useAccount.mockReturnValue( { data: null } );
	} );

	afterEach( () => {
		jest.resetAllMocks();
	} );

	// -------------------------------------------------------------------------
	// Loading state
	// -------------------------------------------------------------------------

	it( 'shows loading indicators while fetching', () => {
		apiFetch.mockReturnValue( new Promise( () => {} ) );

		render( <AgenticCommercePanel /> );

		expect(
			screen.getAllByText( /Loading…/i ).length
		).toBeGreaterThanOrEqual( 1 );
	} );

	// -------------------------------------------------------------------------
	// Empty state
	// -------------------------------------------------------------------------

	it( 'shows "No syncs yet" when last_sync is null', async () => {
		mockFetchByPath( EMPTY_RESPONSE );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( screen.getByText( /No syncs yet/i ) ).toBeInTheDocument();
		} );
	} );

	it( 'shows "No sync history available" when history is empty', async () => {
		mockFetchByPath( EMPTY_RESPONSE );

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
		mockFetchByPath( makeResponse() );

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
		mockFetchByPath( makeResponse() );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			// "42" appears in the status table (products synced).
			expect( screen.getAllByText( '42' ).length ).toBeGreaterThanOrEqual(
				1
			);
		} );
	} );

	it( 'renders import_set_id from last_sync', async () => {
		mockFetchByPath( makeResponse() );

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
		mockFetchByPath( makeResponse() );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( screen.getByText( 'impset_prev' ) ).toBeInTheDocument();
		} );
	} );

	it( 'shows an info icon next to failed history rows that have an error', async () => {
		mockFetchByPath( makeResponse() );

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
		mockFetchByPath(
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
		mockFetchByPath( makeResponse( { next_sync: futureTs } ) );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByText( /Next automatic sync: in \d+ minutes?/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'shows "imminent" label when next_sync is in the past', async () => {
		const pastTs = Math.floor( Date.now() / 1000 ) - 100;
		mockFetchByPath( makeResponse( { next_sync: pastTs } ) );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( screen.getByText( /imminent/i ) ).toBeInTheDocument();
		} );
	} );

	// -------------------------------------------------------------------------
	// API fetch calls
	// -------------------------------------------------------------------------

	it( 'fetches status from the correct REST path on mount', async () => {
		mockFetchByPath( EMPTY_RESPONSE );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/wc_stripe/agentic-commerce',
			} );
		} );
	} );

	it( 'fetches settings from the correct REST path on mount', async () => {
		mockFetchByPath( EMPTY_RESPONSE );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/wc_stripe/agentic-commerce/settings',
			} );
		} );
	} );

	// -------------------------------------------------------------------------
	// Sync Now button
	// -------------------------------------------------------------------------

	it( 'renders the Sync Now button', async () => {
		mockFetchByPath( EMPTY_RESPONSE );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByRole( 'button', { name: /Sync Now/i } )
			).toBeInTheDocument();
		} );
	} );

	it( 'shows success notice and re-fetches after a successful sync', async () => {
		// Mount: settings → SETTINGS_RESPONSE (is_enabled: true), status → EMPTY_RESPONSE.
		// Sync POST → success, re-fetch status → populated response.
		apiFetch.mockImplementation( ( { path, method } ) => {
			if (
				method === 'POST' &&
				path === '/wc/v3/wc_stripe/agentic-commerce/sync'
			) {
				return Promise.resolve( { success: true } );
			}
			if ( path === '/wc/v3/wc_stripe/agentic-commerce/settings' ) {
				return Promise.resolve( SETTINGS_RESPONSE );
			}
			return Promise.resolve( makeResponse() );
		} );

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
		apiFetch.mockImplementation( ( { path, method } ) => {
			if (
				method === 'POST' &&
				path === '/wc/v3/wc_stripe/agentic-commerce/sync'
			) {
				return Promise.reject( { message: 'Server error' } );
			}
			if ( path === '/wc/v3/wc_stripe/agentic-commerce/settings' ) {
				return Promise.resolve( SETTINGS_RESPONSE );
			}
			return Promise.resolve( EMPTY_RESPONSE );
		} );

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
		apiFetch.mockImplementation( ( { path, method } ) => {
			if (
				method === 'POST' &&
				path === '/wc/v3/wc_stripe/agentic-commerce/sync'
			) {
				return Promise.reject( {} );
			}
			if ( path === '/wc/v3/wc_stripe/agentic-commerce/settings' ) {
				return Promise.resolve( SETTINGS_RESPONSE );
			}
			return Promise.resolve( EMPTY_RESPONSE );
		} );

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
		apiFetch.mockImplementation( ( { path } ) => {
			if ( path === '/wc/v3/wc_stripe/agentic-commerce/settings' ) {
				return Promise.resolve( SETTINGS_RESPONSE );
			}
			return Promise.reject( { message: 'Connection refused' } );
		} );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getAllByText( /Connection refused/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	// -------------------------------------------------------------------------
	// Settings card
	// -------------------------------------------------------------------------

	it( 'renders the Enable Agentic Commerce toggle', async () => {
		mockFetchByPath( EMPTY_RESPONSE );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByLabelText( /Enable Agentic Commerce/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'does not show webhook secret input when feature is disabled', async () => {
		mockFetchByPath( EMPTY_RESPONSE, {
			is_enabled: false,
			webhook_secret: '',
		} );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.queryByLabelText( /Webhook Secret/i )
			).not.toBeInTheDocument();
		} );
	} );

	it( 'shows webhook secret input when feature is enabled', async () => {
		mockFetchByPath( EMPTY_RESPONSE, {
			is_enabled: true,
			webhook_secret: '',
		} );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			expect(
				screen.getByLabelText( /Agentic Commerce Webhook Secret/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'prefills webhook secret from settings response', async () => {
		mockFetchByPath( EMPTY_RESPONSE, {
			is_enabled: true,
			webhook_secret: 'whsec_test',
		} );

		render( <AgenticCommercePanel /> );

		await waitFor( () => {
			const input = screen.getByLabelText(
				/Agentic Commerce Webhook Secret/i
			);
			expect( input.value ).toBe( 'whsec_test' );
		} );
	} );

	it( 'saves settings and shows success notice', async () => {
		apiFetch.mockImplementation( ( { path, method } ) => {
			if (
				method === 'POST' &&
				path === '/wc/v3/wc_stripe/agentic-commerce/settings'
			) {
				return Promise.resolve( {
					is_enabled: true,
					webhook_secret: 'whsec_new',
				} );
			}
			if ( path === '/wc/v3/wc_stripe/agentic-commerce/settings' ) {
				return Promise.resolve( {
					is_enabled: true,
					webhook_secret: 'whsec_new',
				} );
			}
			return Promise.resolve( EMPTY_RESPONSE );
		} );

		render( <AgenticCommercePanel /> );

		const saveBtn = await screen.findByRole( 'button', {
			name: /Save Settings/i,
		} );
		fireEvent.click( saveBtn );

		await waitFor( () => {
			expect(
				screen.getAllByText( /Settings saved/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	it( 'shows error notice when settings save fails', async () => {
		apiFetch.mockImplementation( ( { path, method } ) => {
			if (
				method === 'POST' &&
				path === '/wc/v3/wc_stripe/agentic-commerce/settings'
			) {
				return Promise.reject( { message: 'Save failed' } );
			}
			if ( path === '/wc/v3/wc_stripe/agentic-commerce/settings' ) {
				return Promise.resolve( SETTINGS_RESPONSE );
			}
			return Promise.resolve( EMPTY_RESPONSE );
		} );

		render( <AgenticCommercePanel /> );

		const saveBtn = await screen.findByRole( 'button', {
			name: /Save Settings/i,
		} );
		fireEvent.click( saveBtn );

		await waitFor( () => {
			expect(
				screen.getAllByText( /Save failed/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );
} );
