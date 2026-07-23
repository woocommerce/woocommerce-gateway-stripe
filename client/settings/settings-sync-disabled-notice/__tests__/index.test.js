import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import SettingsSyncDisabledNotice from '..';
import { useDispatch } from '@wordpress/data';
import { recordEvent } from 'wcstripe/tracking';
import { useTestMode } from 'wcstripe/data';

const noticesDispatch = {
	createErrorNotice: jest.fn(),
};

jest.mock( '@wordpress/data' );

jest.mock( 'wcstripe/data', () => ( {
	useTestMode: jest.fn(),
} ) );

jest.mock( 'wcstripe/tracking', () => ( {
	recordEvent: jest.fn(),
} ) );

describe( 'SettingsSyncDisabledNotice', () => {
	const globalValues = global.wc_stripe_settings_params;

	beforeEach( () => {
		global.wc_stripe_settings_params = {
			...globalValues,
			is_pmc_sync_disabled: '1',
			oauth_nonce: 'test-nonce',
		};
		global.ajaxurl = '/wp-admin/admin-ajax.php';
		useDispatch.mockImplementation( ( storeName ) => {
			if ( storeName === 'core/notices' ) {
				return noticesDispatch;
			}

			return {};
		} );
		useTestMode.mockReturnValue( [ false, jest.fn() ] );
	} );

	afterEach( () => {
		jest.clearAllMocks();
		global.wc_stripe_settings_params = globalValues;
	} );

	it( 'should render the notice when settings sync is disabled', () => {
		render( <SettingsSyncDisabledNotice /> );

		expect(
			screen.queryAllByText(
				'Your payment method settings are no longer synced with your Stripe account. To restore syncing, please re-authenticate your Stripe account connection.'
			)?.[ 0 ]
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Re-authenticate' } )
		).toBeInTheDocument();
	} );

	it( 'should not render the notice when settings sync is not disabled', () => {
		global.wc_stripe_settings_params.is_pmc_sync_disabled = '';

		const { container } = render( <SettingsSyncDisabledNotice /> );

		expect( container.firstChild ).toBeNull();
	} );

	it( 'should fetch the OAuth URL and redirect on button click', async () => {
		Object.defineProperty( window, 'location', {
			value: { assign: jest.fn() },
		} );

		const oauthUrl = 'http://example.com/oauth';
		global.jQuery = {
			ajax: jest.fn().mockResolvedValue( {
				success: true,
				data: { oauth_url: oauthUrl },
			} ),
		};

		render( <SettingsSyncDisabledNotice /> );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Re-authenticate' } )
		);

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_reconnect_button_click',
			{
				source: 'settings-sync-disabled-notice',
				mode: 'live',
			}
		);
		expect( global.jQuery.ajax ).toHaveBeenCalledWith( {
			url: '/wp-admin/admin-ajax.php',
			method: 'POST',
			data: {
				action: 'wc_stripe_get_oauth_url',
				mode: 'live',
				nonce: 'test-nonce',
			},
		} );
		await waitFor( () =>
			expect( window.location.assign ).toHaveBeenCalledWith( oauthUrl )
		);
	} );

	it( 'should use test mode when test mode is enabled', async () => {
		useTestMode.mockReturnValue( [ true, jest.fn() ] );
		global.jQuery = {
			ajax: jest.fn().mockResolvedValue( {
				success: true,
				data: { oauth_url: 'http://example.com/oauth' },
			} ),
		};

		render( <SettingsSyncDisabledNotice /> );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Re-authenticate' } )
		);

		expect( global.jQuery.ajax ).toHaveBeenCalledWith(
			expect.objectContaining( {
				data: expect.objectContaining( { mode: 'test' } ),
			} )
		);
	} );

	it( 'should show an error notice when fetching the OAuth URL fails', async () => {
		global.jQuery = {
			ajax: jest.fn().mockResolvedValue( { success: false } ),
		};

		render( <SettingsSyncDisabledNotice /> );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Re-authenticate' } )
		);

		await waitFor( () =>
			expect( noticesDispatch.createErrorNotice ).toHaveBeenCalledWith(
				'There was an error. Please reload the page and try again.'
			)
		);
	} );
} );
