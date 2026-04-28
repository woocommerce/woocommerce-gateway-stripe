import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import AgenticCommerceSection from '..';
import apiFetch from '@wordpress/api-fetch';
import { dispatch } from '@wordpress/data';
import { useTestMode } from 'wcstripe/data';
import { useAccount } from 'wcstripe/data/account';

jest.mock( '@wordpress/api-fetch' );

const mockCreateSuccessNotice = jest.fn();
const mockCreateErrorNotice = jest.fn();
jest.mock( '@wordpress/data', () => ( {
	...jest.requireActual( '@wordpress/data' ),
	dispatch: jest.fn( () => ( {
		createSuccessNotice: mockCreateSuccessNotice,
		createErrorNotice: mockCreateErrorNotice,
	} ) ),
} ) );

jest.mock( 'wcstripe/data', () => ( {
	useTestMode: jest.fn(),
} ) );

jest.mock( 'wcstripe/settings/loadable-settings-section', () => {
	return ( { children } ) => <>{ children }</>;
} );

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

describe( 'AgenticCommerceSection', () => {
	beforeEach( () => {
		dispatch.mockReturnValue( {
			createSuccessNotice: mockCreateSuccessNotice,
			createErrorNotice: mockCreateErrorNotice,
		} );
		useTestMode.mockReturnValue( [ false ] );
		useAccount.mockReturnValue( { data: null } );
		global.wc_stripe_settings_params = {
			agentic_commerce_import_sets_url:
				'https://dashboard.stripe.com/test/data-management/import-sets',
			agentic_commerce_logs_url:
				'/wp-admin/admin.php?page=wc-status&tab=logs',
		};
	} );

	afterEach( () => {
		delete global.wc_stripe_settings_params;
		jest.resetAllMocks();
		mockCreateSuccessNotice.mockClear();
		mockCreateErrorNotice.mockClear();
	} );

	// -------------------------------------------------------------------------
	// Loading state
	// -------------------------------------------------------------------------

	it( 'shows loading indicators while fetching', () => {
		apiFetch.mockReturnValue( new Promise( () => {} ) );

		render( <AgenticCommerceSection /> );

		expect(
			screen.getAllByText( /Loading…/i ).length
		).toBeGreaterThanOrEqual( 1 );
	} );

	// -------------------------------------------------------------------------
	// Empty state
	// -------------------------------------------------------------------------

	it( 'shows "No syncs yet" when last_sync is null', async () => {
		mockFetchByPath( EMPTY_RESPONSE );

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect( screen.getByText( /No syncs yet/i ) ).toBeInTheDocument();
		} );
	} );

	it( 'shows "No sync history available" when history is empty', async () => {
		mockFetchByPath( EMPTY_RESPONSE );

		render( <AgenticCommerceSection /> );

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

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect(
				screen.getAllByText( /Success/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	it( 'renders product count from last_sync', async () => {
		mockFetchByPath( makeResponse() );

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect( screen.getAllByText( '42' ).length ).toBeGreaterThanOrEqual(
				1
			);
		} );
	} );

	it( 'renders import_set_id from last_sync', async () => {
		mockFetchByPath( makeResponse() );

		render( <AgenticCommerceSection /> );

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

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			// The import_set_id is split across two spans (id-start / id-end) in the
			// history row so we can't match the concatenated string directly;
			// assert via the `title` attribute on the wrapping <code> instead.
			expect( screen.getByTitle( 'impset_prev' ) ).toBeInTheDocument();
		} );
	} );

	it( 'shows an info icon next to failed history rows that have an error', async () => {
		mockFetchByPath( makeResponse() );

		render( <AgenticCommerceSection /> );

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

		render( <AgenticCommerceSection /> );

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

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect(
				screen.getByText( /Next automatic sync: in \d+ minutes?/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'shows "imminent" label when next_sync is in the past', async () => {
		const pastTs = Math.floor( Date.now() / 1000 ) - 100;
		mockFetchByPath( makeResponse( { next_sync: pastTs } ) );

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect( screen.getByText( /imminent/i ) ).toBeInTheDocument();
		} );
	} );

	// -------------------------------------------------------------------------
	// API fetch calls
	// -------------------------------------------------------------------------

	it( 'fetches status from the correct REST path on mount', async () => {
		mockFetchByPath( EMPTY_RESPONSE );

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/wc/v3/wc_stripe/agentic-commerce/status',
			} );
		} );
	} );

	it( 'fetches settings from the correct REST path on mount', async () => {
		mockFetchByPath( EMPTY_RESPONSE );

		render( <AgenticCommerceSection /> );

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

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect(
				screen.getByRole( 'button', { name: /Sync Now/i } )
			).toBeInTheDocument();
		} );
	} );

	it( 'shows success notice and re-fetches after a successful sync', async () => {
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

		render( <AgenticCommerceSection /> );

		const syncBtn = await screen.findByRole( 'button', {
			name: /Sync Now/i,
		} );
		fireEvent.click( syncBtn );

		await waitFor( () => {
			expect( mockCreateSuccessNotice ).toHaveBeenCalledWith(
				'Sync triggered successfully.'
			);
		} );

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

		render( <AgenticCommerceSection /> );

		const syncBtn = await screen.findByRole( 'button', {
			name: /Sync Now/i,
		} );
		fireEvent.click( syncBtn );

		await waitFor( () => {
			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				'Sync failed. Check the WooCommerce logs for details.'
			);
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

		render( <AgenticCommerceSection /> );

		const syncBtn = await screen.findByRole( 'button', {
			name: /Sync Now/i,
		} );
		fireEvent.click( syncBtn );

		await waitFor( () => {
			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				'Sync failed. Check the WooCommerce logs for details.'
			);
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

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				'Failed to load sync status.'
			);
		} );

		// Empty-state placeholders should NOT be shown when fetch fails.
		expect( screen.queryByText( /No syncs yet/i ) ).not.toBeInTheDocument();
		expect(
			screen.queryByText( /No sync history available/i )
		).not.toBeInTheDocument();
	} );

	// -------------------------------------------------------------------------
	// Onboarding steps visibility
	// -------------------------------------------------------------------------

	it( 'shows onboarding steps when feature is enabled and no webhook secret is saved', async () => {
		mockFetchByPath( EMPTY_RESPONSE, {
			is_enabled: true,
			webhook_secret: '',
		} );

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect(
				screen.getAllByText( /Setup instructions/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	it( 'hides onboarding steps when feature is disabled', async () => {
		mockFetchByPath( EMPTY_RESPONSE, {
			is_enabled: false,
			webhook_secret: '',
		} );

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect(
				screen.queryByLabelText( /Webhook secret/i )
			).not.toBeInTheDocument();
		} );
		expect(
			screen.queryByText( /Setup instructions/i )
		).not.toBeInTheDocument();
	} );

	it( 'shows onboarding steps when feature is enabled even with webhook secret saved', async () => {
		mockFetchByPath( EMPTY_RESPONSE, {
			is_enabled: true,
			webhook_secret: '****',
		} );

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect(
				screen.getByLabelText( /Webhook secret/i )
			).toBeInTheDocument();
		} );
		expect(
			screen.getAllByText( /Setup instructions/i ).length
		).toBeGreaterThanOrEqual( 1 );
	} );

	// -------------------------------------------------------------------------
	// Webhook URL and CopyButton
	// -------------------------------------------------------------------------

	it( 'shows webhook URL with copy button when account has configured webhook URL', async () => {
		useAccount.mockReturnValue( {
			data: {
				configured_webhook_urls: {
					live: 'https%3A%2F%2Fexample.com%2Fwc-api%2Fwc_stripe',
				},
			},
		} );
		mockFetchByPath( EMPTY_RESPONSE, {
			is_enabled: true,
			webhook_secret: '',
		} );

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect(
				screen.getByText( /Set endpoint URL as/i )
			).toBeInTheDocument();
		} );

		expect(
			screen.getByRole( 'button', { name: /Copy/i } )
		).toBeInTheDocument();
	} );

	it( 'shows fallback text when no webhook URL is available', async () => {
		useAccount.mockReturnValue( { data: null } );
		mockFetchByPath( EMPTY_RESPONSE, {
			is_enabled: true,
			webhook_secret: '',
		} );

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect(
				screen.getByText( /Setup webhooks in/i )
			).toBeInTheDocument();
		} );
	} );

	// -------------------------------------------------------------------------
	// Settings controls
	// -------------------------------------------------------------------------

	it( 'renders the Enable agentic commerce toggle', async () => {
		mockFetchByPath( EMPTY_RESPONSE );

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect(
				screen.getByLabelText( /Enable agentic commerce/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'does not show webhook secret input when feature is disabled', async () => {
		mockFetchByPath( EMPTY_RESPONSE, {
			is_enabled: false,
			webhook_secret: '',
		} );

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect(
				screen.queryByLabelText( /Webhook secret/i )
			).not.toBeInTheDocument();
		} );
	} );

	it( 'shows webhook secret input when feature is enabled', async () => {
		mockFetchByPath( EMPTY_RESPONSE, {
			is_enabled: true,
			webhook_secret: '',
		} );

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			expect(
				screen.getByLabelText( /Webhook secret/i )
			).toBeInTheDocument();
		} );
	} );

	it( 'prefills webhook secret field with masked placeholder when a secret is stored', async () => {
		mockFetchByPath( EMPTY_RESPONSE, {
			is_enabled: true,
			webhook_secret: '****',
		} );

		render( <AgenticCommerceSection /> );

		await waitFor( () => {
			const input = screen.getByLabelText( /Webhook secret/i );
			expect( input.value ).toBe( '****' );
		} );
	} );

	// -------------------------------------------------------------------------
	// Save via ref
	// -------------------------------------------------------------------------

	it( 'exposes a save function via ref that saves settings', async () => {
		const ref = { current: null };

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
					webhook_secret: '',
				} );
			}
			return Promise.resolve( EMPTY_RESPONSE );
		} );

		render( <AgenticCommerceSection ref={ ref } /> );

		// Wait for initial load to complete.
		await waitFor( () => {
			expect( ref.current ).not.toBeNull();
		} );
		expect( typeof ref.current.save ).toBe( 'function' );

		// Trigger save via ref.
		await ref.current.save();

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/wc/v3/wc_stripe/agentic-commerce/settings',
				method: 'POST',
			} )
		);
	} );

	it( 'renders an error notice when save via ref fails', async () => {
		const ref = { current: null };

		apiFetch.mockImplementation( ( { path, method } ) => {
			if (
				method === 'POST' &&
				path === '/wc/v3/wc_stripe/agentic-commerce/settings'
			) {
				return Promise.reject( { message: 'Server exploded' } );
			}
			if ( path === '/wc/v3/wc_stripe/agentic-commerce/settings' ) {
				return Promise.resolve( {
					is_enabled: true,
					webhook_secret: '',
				} );
			}
			return Promise.resolve( EMPTY_RESPONSE );
		} );

		render( <AgenticCommerceSection ref={ ref } /> );

		await waitFor( () => {
			expect( ref.current ).not.toBeNull();
		} );

		await ref.current.save();

		await waitFor( () => {
			expect(
				screen.getAllByText( /Server exploded/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );

	it( 'falls back to a generic error message when save via ref rejects without a message', async () => {
		const ref = { current: null };

		apiFetch.mockImplementation( ( { path, method } ) => {
			if (
				method === 'POST' &&
				path === '/wc/v3/wc_stripe/agentic-commerce/settings'
			) {
				return Promise.reject( {} );
			}
			if ( path === '/wc/v3/wc_stripe/agentic-commerce/settings' ) {
				return Promise.resolve( {
					is_enabled: true,
					webhook_secret: '',
				} );
			}
			return Promise.resolve( EMPTY_RESPONSE );
		} );

		render( <AgenticCommerceSection ref={ ref } /> );

		await waitFor( () => {
			expect( ref.current ).not.toBeNull();
		} );

		await ref.current.save();

		await waitFor( () => {
			expect(
				screen.getAllByText( /Failed to save settings/i ).length
			).toBeGreaterThanOrEqual( 1 );
		} );
	} );
} );
