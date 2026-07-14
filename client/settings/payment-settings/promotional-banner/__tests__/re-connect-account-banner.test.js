import { render, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useDispatch } from '@wordpress/data';
import { ReConnectAccountBanner } from 'wcstripe/settings/payment-settings/promotional-banner/re-connect-account-banner';
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

// Mock global variables
global.wc_stripe_settings_params = {
	oauth_nonce: 'test-nonce',
};
global.ajaxurl = '/wp-admin/admin-ajax.php';

describe( 'Reconnect banner', () => {
	beforeEach( () => {
		useDispatch.mockImplementation( ( storeName ) => {
			if ( storeName === 'core/notices' ) {
				return noticesDispatch;
			}

			return {};
		} );
		useTestMode.mockReturnValue( [ true, jest.fn() ] );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should render the Reconnect promotional banner', () => {
		const { getByText } = render( <ReConnectAccountBanner /> );
		expect(
			getByText( 'Make your store more secure' )
		).toBeInTheDocument();
		expect(
			getByText(
				'Re-connect your Stripe account using the new authentication flow by clicking the "Re-authenticate" button and make your store safer.'
			)
		).toBeInTheDocument();
		expect( getByText( 'Re-authenticate' ) ).toBeInTheDocument();
	} );

	it( 'should fetch OAuth URL and redirect on button click in test mode', async () => {
		// Keep the original function at hand.
		const assign = window.location.assign;

		Object.defineProperty( window, 'location', {
			value: { assign: jest.fn() },
		} );

		const oauthUrl = 'http://example.com/test-oauth';

		// Mock jQuery.ajax
		global.jQuery = {
			ajax: jest.fn().mockResolvedValue( {
				success: true,
				data: { oauth_url: oauthUrl },
			} ),
		};

		const { getByText } = render( <ReConnectAccountBanner /> );
		const reconnectButton = getByText( 'Re-authenticate' );
		await userEvent.click( reconnectButton );

		expect( recordEvent ).toHaveBeenNthCalledWith(
			1,
			'wcstripe_create_or_connect_test_account_click',
			{}
		);
		expect( recordEvent ).toHaveBeenNthCalledWith(
			2,
			'wcstripe_reconnect_button_click',
			{
				source: 're-connect-account-banner',
				mode: 'test',
			}
		);

		await waitFor( () => {
			expect( window.location.assign ).toHaveBeenCalledWith( oauthUrl );
		} );

		expect( global.jQuery.ajax ).toHaveBeenCalledWith(
			expect.objectContaining( {
				data: expect.objectContaining( {
					action: 'wc_stripe_get_oauth_url',
					mode: 'test',
				} ),
			} )
		);

		// Set the original function back to keep further tests working as expected.
		Object.defineProperty( window, 'location', {
			value: { assign },
		} );
	} );

	it( 'should fetch OAuth URL and redirect on button click in live mode', async () => {
		useTestMode.mockReturnValue( [ false, jest.fn() ] );

		// Keep the original function at hand.
		const assign = window.location.assign;

		Object.defineProperty( window, 'location', {
			value: { assign: jest.fn() },
		} );

		const oauthUrl = 'http://example.com/live-oauth';

		// Mock jQuery.ajax
		global.jQuery = {
			ajax: jest.fn().mockResolvedValue( {
				success: true,
				data: { oauth_url: oauthUrl },
			} ),
		};

		const { getByText } = render( <ReConnectAccountBanner /> );
		const reconnectButton = getByText( 'Re-authenticate' );
		await userEvent.click( reconnectButton );

		expect( recordEvent ).toHaveBeenNthCalledWith(
			1,
			'wcstripe_create_or_connect_account_click',
			{}
		);
		expect( recordEvent ).toHaveBeenNthCalledWith(
			2,
			'wcstripe_reconnect_button_click',
			{
				source: 're-connect-account-banner',
				mode: 'live',
			}
		);

		await waitFor( () => {
			expect( window.location.assign ).toHaveBeenCalledWith( oauthUrl );
		} );

		expect( global.jQuery.ajax ).toHaveBeenCalledWith(
			expect.objectContaining( {
				data: expect.objectContaining( {
					action: 'wc_stripe_get_oauth_url',
					mode: 'live',
				} ),
			} )
		);

		// Set the original function back to keep further tests working as expected.
		Object.defineProperty( window, 'location', {
			value: { assign },
		} );
	} );

	it( 'should create error notice when AJAX request fails', async () => {
		// Mock jQuery.ajax to fail
		global.jQuery = {
			ajax: jest.fn().mockRejectedValue( new Error( 'Network error' ) ),
		};

		const { getByText } = render( <ReConnectAccountBanner /> );
		const reconnectButton = getByText( 'Re-authenticate' );
		await userEvent.click( reconnectButton );

		await waitFor( () => {
			expect( noticesDispatch.createErrorNotice ).toHaveBeenCalledWith(
				'There was an error. Please reload the page and try again.'
			);
		} );
	} );

	it( 'should create error notice when AJAX returns error response', async () => {
		// Mock jQuery.ajax to return error
		global.jQuery = {
			ajax: jest.fn().mockResolvedValue( {
				success: false,
				data: { message: 'Server error' },
			} ),
		};

		const { getByText } = render( <ReConnectAccountBanner /> );
		const reconnectButton = getByText( 'Re-authenticate' );
		await userEvent.click( reconnectButton );

		await waitFor( () => {
			expect( noticesDispatch.createErrorNotice ).toHaveBeenCalledWith(
				'There was an error. Please reload the page and try again.'
			);
		} );
	} );
} );
