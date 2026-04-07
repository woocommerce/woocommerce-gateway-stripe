import apiFetch from '@wordpress/api-fetch';
import { dismissNotice } from 'wcstripe/utils';

jest.mock( '@wordpress/api-fetch' );

describe( 'dismissNotice', () => {
	afterEach( () => {
		jest.clearAllMocks();
		delete global.wc_stripe_settings_params;
	} );

	it( 'should call apiFetch with the correct path and method', () => {
		apiFetch.mockImplementation( () => Promise.resolve( {} ) );

		dismissNotice( 'wc_stripe_show_test_notice', jest.fn() );

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wc/v3/wc_stripe/settings/notice',
			method: 'POST',
			data: { wc_stripe_show_test_notice: 'no' },
		} );
	} );

	it( 'should call the callback in the finally handler', async () => {
		const callback = jest.fn();

		apiFetch.mockImplementation( () => Promise.resolve( {} ) );

		dismissNotice( 'wc_stripe_show_test_notice', callback );

		// Flush promises to ensure finally handler has executed
		await new Promise( process.nextTick );

		expect( callback ).toHaveBeenCalled();
	} );

	it( 'should update wc_stripe_settings_params when dismissing a known notice', async () => {
		global.wc_stripe_settings_params = {
			show_bnpl_promotional_banner: '1',
			show_oc_promotional_banner: '1',
			show_stripe_tax_banner: '1',
			show_customization_notice: true,
			show_optimized_checkout_notice: true,
		};

		apiFetch.mockImplementation( () => Promise.resolve( {} ) );

		dismissNotice( 'wc_stripe_show_bnpl_promotion_banner', jest.fn() );
		await new Promise( process.nextTick );

		expect(
			global.wc_stripe_settings_params.show_bnpl_promotional_banner
		).toBe( false );
		// Other params should remain unchanged.
		expect(
			global.wc_stripe_settings_params.show_oc_promotional_banner
		).toBe( '1' );
	} );

	it( 'should update wc_stripe_settings_params for OC promotion banner', async () => {
		global.wc_stripe_settings_params = {
			show_oc_promotional_banner: '1',
		};

		apiFetch.mockImplementation( () => Promise.resolve( {} ) );

		dismissNotice( 'wc_stripe_show_oc_promotion_banner', jest.fn() );
		await new Promise( process.nextTick );

		expect(
			global.wc_stripe_settings_params.show_oc_promotional_banner
		).toBe( false );
	} );

	it( 'should update wc_stripe_settings_params for Stripe Tax banner', async () => {
		global.wc_stripe_settings_params = {
			show_stripe_tax_banner: '1',
		};

		apiFetch.mockImplementation( () => Promise.resolve( {} ) );

		dismissNotice( 'wc_stripe_show_stripe_tax_banner', jest.fn() );
		await new Promise( process.nextTick );

		expect( global.wc_stripe_settings_params.show_stripe_tax_banner ).toBe(
			false
		);
	} );

	it( 'should not throw when wc_stripe_settings_params is undefined', async () => {
		apiFetch.mockImplementation( () => Promise.resolve( {} ) );

		dismissNotice( 'wc_stripe_show_bnpl_promotion_banner', jest.fn() );
		await new Promise( process.nextTick );

		// The API call should still be made even without the global.
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wc/v3/wc_stripe/settings/notice',
			method: 'POST',
			data: { wc_stripe_show_bnpl_promotion_banner: 'no' },
		} );
		// The global should remain absent (not created as a side-effect).
		expect( global.wc_stripe_settings_params ).toBeUndefined();
	} );

	it( 'should handle null callback gracefully', async () => {
		apiFetch.mockImplementation( () => Promise.resolve( {} ) );

		dismissNotice( 'wc_stripe_show_test_notice', null );
		await new Promise( process.nextTick );

		// The API call should still be made with null callback.
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wc/v3/wc_stripe/settings/notice',
			method: 'POST',
			data: { wc_stripe_show_test_notice: 'no' },
		} );
	} );

	it( 'should not update wc_stripe_settings_params when apiFetch rejects', async () => {
		global.wc_stripe_settings_params = {
			show_bnpl_promotional_banner: '1',
		};

		const callback = jest.fn();
		apiFetch.mockImplementation( () =>
			Promise.reject( new Error( 'fail' ) )
		);

		dismissNotice( 'wc_stripe_show_bnpl_promotion_banner', callback );
		await new Promise( process.nextTick );

		// The global should remain unchanged because the API call failed.
		expect(
			global.wc_stripe_settings_params.show_bnpl_promotional_banner
		).toBe( '1' );
		// The callback should still be called (via .finally) so callers
		// can clean up regardless of success or failure.
		expect( callback ).toHaveBeenCalled();
	} );
} );
