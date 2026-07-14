import apiFetch from '@wordpress/api-fetch';
import { dismissNotice } from 'wcstripe/utils';

jest.mock( '@wordpress/api-fetch' );

describe( 'dismissNotice', () => {
	afterEach( () => {
		jest.clearAllMocks();
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
} );
