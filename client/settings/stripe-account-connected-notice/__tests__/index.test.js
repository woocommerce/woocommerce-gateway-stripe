import React from 'react';
import { render } from '@testing-library/react';

const mockGetStorageWithExpiration = jest.fn();
const mockSetStorageWithExpiration = jest.fn();
const mockGetQuery = jest.fn();
const mockCreateSuccessNotice = jest.fn();

jest.mock( '@wordpress/data', () => ( {
	dispatch: () => ( {
		createSuccessNotice: mockCreateSuccessNotice,
	} ),
} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: ( ...args ) => mockGetQuery( ...args ),
} ) );

jest.mock( '@woocommerce/settings', () => ( {
	getAdminLink: ( path ) => `/wp-admin/${ path }`,
} ) );

jest.mock( 'wcstripe/stripe-utils/utils', () => ( {
	getStorageWithExpiration: ( ...args ) =>
		mockGetStorageWithExpiration( ...args ),
	setStorageWithExpiration: ( ...args ) =>
		mockSetStorageWithExpiration( ...args ),
} ) );

const LOCAL_STORAGE_KEY = 'wc_stripe_is_onboarding_through_wc_setup';

const flushPromises = () =>
	new Promise( ( resolve ) => setImmediate( resolve ) );

describe( 'StripeAccountConnectedNotice', () => {
	const originalLocation = window.location;
	const originalLocalStorage = window.localStorage;
	const mockRemoveItem = jest.fn();

	beforeAll( () => {
		// Replace global objects once for all specs; restore in afterAll.
		Object.defineProperty( window, 'localStorage', {
			value: { removeItem: mockRemoveItem },
			writable: true,
		} );
	} );

	beforeEach( () => {
		jest.clearAllMocks();

		// Default values for every test.
		mockGetQuery.mockReturnValue( {} );
		mockGetStorageWithExpiration.mockReturnValue( null );

		// Ensure we have a predictable window.location for query parsing.
		delete window.location;
		window.location = { search: '' };

		global.wc_stripe_settings_params = {
			is_payments_onboarding_task_completed: '0',
		};
	} );

	afterAll( () => {
		window.location = originalLocation;
		window.localStorage = originalLocalStorage;
	} );

	const renderNotice = async () => {
		const StripeAccountConnectedNotice = ( await import( '..' ) ).default;
		render( <StripeAccountConnectedNotice /> );
		await flushPromises();
	};

	it( 'shows success notice when Stripe is connected and user is onboarding via WC', async () => {
		mockGetQuery.mockReturnValue( { wc_stripe_connected: 'true' } );
		mockGetStorageWithExpiration.mockReturnValue( 'true' );

		await renderNotice();

		expect( mockCreateSuccessNotice ).toHaveBeenCalledWith(
			'Stripe Account Connected',
			{
				id: 'WOOCOMMERCE_STRIPE_ACCOUNT_CONNECTED_NOTICE',
				actions: [
					{
						url: '/wp-admin/admin.php?page=wc-admin',
						label: 'Continue setting up your store',
					},
				],
			}
		);

		expect( mockRemoveItem ).toHaveBeenCalledWith( LOCAL_STORAGE_KEY );
	} );

	it( 'does not show notice when Stripe is not connected', async () => {
		mockGetStorageWithExpiration.mockReturnValue( 'true' );

		await renderNotice();

		expect( mockCreateSuccessNotice ).not.toHaveBeenCalled();
		expect( mockRemoveItem ).not.toHaveBeenCalled();
	} );

	it( 'does not show notice when the user is not onboarding via WC', async () => {
		mockGetQuery.mockReturnValue( { wc_stripe_connected: 'true' } );

		await renderNotice();

		expect( mockCreateSuccessNotice ).not.toHaveBeenCalled();
		expect( mockRemoveItem ).not.toHaveBeenCalled();
	} );
} );
