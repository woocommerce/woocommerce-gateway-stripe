import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ConnectButton from '../connect-button';

jest.mock( 'wcstripe/tracking', () => ( {
	recordEvent: jest.fn(),
} ) );

// Mock global variables
global.wc_stripe_settings_params = {
	oauth_nonce: 'test-nonce',
};
global.ajaxurl = '/wp-admin/admin-ajax.php';

const oauthUrl =
	'https://connect.stripe.com/oauth/v2/authorize?response_type=code&client_id=ca_test&scope=read_write&state=abc123';

describe( 'ConnectButton', () => {
	let originalOpen;
	let originalLocation;

	beforeEach( () => {
		jest.clearAllMocks();

		originalOpen = window.open;
		originalLocation = window.location;

		Object.defineProperty( window, 'location', {
			value: {
				protocol: 'https:',
				origin: 'https://example.com',
				assign: jest.fn(),
				reload: jest.fn(),
			},
			writable: true,
			configurable: true,
		} );

		global.jQuery = {
			ajax: jest.fn().mockResolvedValue( {
				success: true,
				data: { oauth_url: oauthUrl },
			} ),
		};
	} );

	afterEach( () => {
		window.open = originalOpen;
		Object.defineProperty( window, 'location', {
			value: originalLocation,
			writable: true,
			configurable: true,
		} );
	} );

	it( 'opens popup and sets up message listener on success', async () => {
		const mockPopup = { closed: false };
		window.open = jest.fn().mockReturnValue( mockPopup );
		const addEventListenerSpy = jest.spyOn( window, 'addEventListener' );

		render( <ConnectButton testMode={ true } /> );

		await userEvent.click(
			screen.getByText( 'Create or connect a test account' )
		);

		await waitFor( () => {
			expect( window.open ).toHaveBeenCalledWith( oauthUrl, '_blank' );
		} );

		expect( addEventListenerSpy ).toHaveBeenCalledWith(
			'message',
			expect.any( Function )
		);
	} );

	it( 'sends new_tab=1 in the AJAX request', async () => {
		const mockPopup = { closed: false };
		window.open = jest.fn().mockReturnValue( mockPopup );

		render( <ConnectButton testMode={ false } /> );

		await userEvent.click(
			screen.getByText( 'Create or connect an account' )
		);

		await waitFor( () => {
			expect( global.jQuery.ajax ).toHaveBeenCalledWith(
				expect.objectContaining( {
					data: expect.objectContaining( {
						new_tab: '1',
					} ),
				} )
			);
		} );
	} );

	it( 'falls back to same-tab redirect when window.open returns null (popup blocked)', async () => {
		window.open = jest.fn().mockReturnValue( null );

		render( <ConnectButton testMode={ true } /> );

		await userEvent.click(
			screen.getByText( 'Create or connect a test account' )
		);

		await waitFor( () => {
			expect( window.location.assign ).toHaveBeenCalledWith( oauthUrl );
		} );
	} );

	it( 'reloads page when valid wc_stripe_oauth_connected postMessage received', async () => {
		const mockPopup = { closed: false };
		window.open = jest.fn().mockReturnValue( mockPopup );

		render( <ConnectButton testMode={ true } /> );

		await userEvent.click(
			screen.getByText( 'Create or connect a test account' )
		);

		await waitFor( () => {
			expect( window.open ).toHaveBeenCalled();
		} );

		// Simulate a valid postMessage from the same origin.
		window.dispatchEvent(
			new MessageEvent( 'message', {
				origin: 'https://example.com',
				data: { type: 'wc_stripe_oauth_connected', success: true },
			} )
		);

		expect( window.location.reload ).toHaveBeenCalled();
	} );

	it( 'ignores messages from different origins', async () => {
		const mockPopup = { closed: false };
		window.open = jest.fn().mockReturnValue( mockPopup );

		render( <ConnectButton testMode={ true } /> );

		await userEvent.click(
			screen.getByText( 'Create or connect a test account' )
		);

		await waitFor( () => {
			expect( window.open ).toHaveBeenCalled();
		} );

		// Dispatch from a different origin — should be ignored.
		window.dispatchEvent(
			new MessageEvent( 'message', {
				origin: 'https://evil.example.com',
				data: { type: 'wc_stripe_oauth_connected', success: true },
			} )
		);

		expect( window.location.reload ).not.toHaveBeenCalled();
	} );

	it( 'resets loading state when popup is closed manually', async () => {
		const mockPopup = { closed: false };
		window.open = jest.fn().mockReturnValue( mockPopup );

		render( <ConnectButton testMode={ true } /> );

		await userEvent.click(
			screen.getByText( 'Create or connect a test account' )
		);

		// Wait for the popup to open.
		await waitFor( () => {
			expect( window.open ).toHaveBeenCalled();
		} );

		// Button should be in loading/busy state.
		expect(
			screen.getByText( 'Create or connect a test account' )
		).toBeDisabled();

		// Simulate the popup being closed manually.
		mockPopup.closed = true;

		// The interval runs every 500ms. waitFor polls until the assertion passes.
		await waitFor(
			() => {
				expect(
					screen.getByText( 'Create or connect a test account' )
				).not.toBeDisabled();
			},
			{ timeout: 2000, interval: 100 }
		);
	} );
} );
