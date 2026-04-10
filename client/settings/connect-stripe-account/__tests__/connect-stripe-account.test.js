import React, { act } from 'react';
import { screen, render, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ConnectStripeAccount from '..';
import { recordEvent } from 'wcstripe/tracking';

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
} ) );

jest.mock( 'wcstripe/tracking', () => ( {
	recordEvent: jest.fn(),
} ) );

// Mock global variables
global.wc_stripe_settings_params = {
	oauth_nonce: 'test-nonce',
};
global.ajaxurl = '/wp-admin/admin-ajax.php';

describe( 'ConnectStripeAccount', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should render the information', () => {
		render( <ConnectStripeAccount /> );

		expect(
			screen.queryByText( 'Get started with Stripe' )
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				'Connect or create a Stripe account to accept all major debit and credit cards, digital wallets (including Apple Pay and Google Pay), buy now, pay later options (such as Klarna and Affirm), and a wide range of local and international payment methods.'
			)
		).toBeInTheDocument();
	} );

	it( 'should render both the "Create or connect an account" and "Create or connect a test account" buttons', () => {
		// Mock SSL for this test
		const protocol = window.location.protocol;
		Object.defineProperty( window, 'location', {
			value: { ...window.location, protocol: 'https:' },
			writable: true,
		} );

		render( <ConnectStripeAccount /> );

		expect( screen.queryByText( 'Terms of service.' ) ).toBeInTheDocument();

		expect(
			screen.getByText( 'Create or connect an account' )
		).toBeInTheDocument();

		expect(
			screen.getByText( 'Create or connect a test account' )
		).toBeInTheDocument();

		// Restore original protocol
		Object.defineProperty( window, 'location', {
			value: { ...window.location, protocol },
			writable: true,
		} );
	} );

	it( 'should fetch OAuth URL and redirect when clicking on the "Create or connect an account" button', async () => {
		// Keep the original function at hand.
		const assign = window.location.assign;
		const protocol = window.location.protocol;

		Object.defineProperty( window, 'location', {
			value: { assign: jest.fn(), protocol: 'https:' },
			writable: true,
		} );

		const oauthUrl =
			'https://connect.stripe.com/oauth/v2/authorize?response_type=code&client_id=ca_1234&scope=read_write&state=1234';

		// Mock jQuery.ajax
		global.jQuery = {
			ajax: jest.fn().mockResolvedValue( {
				success: true,
				data: { oauth_url: oauthUrl },
			} ),
		};

		render( <ConnectStripeAccount /> );

		const connectAccountButton = screen.getByText(
			'Create or connect an account'
		);
		await userEvent.click( connectAccountButton );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_create_or_connect_account_click',
			{}
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
			value: { assign, protocol },
			writable: true,
		} );
	} );

	it( 'should fetch OAuth URL and redirect when clicking on the "Create or connect a test account" button', async () => {
		// Keep the original function at hand.
		const assign = window.location.assign;

		Object.defineProperty( window, 'location', {
			value: { assign: jest.fn() },
		} );

		const testOauthUrl =
			'https://connect.stripe.com/oauth/v2/authorize?response_type=code&client_id=ca_5678&scope=read_write&state=5678';

		// Mock jQuery.ajax
		global.jQuery = {
			ajax: jest.fn().mockResolvedValue( {
				success: true,
				data: { oauth_url: testOauthUrl },
			} ),
		};

		render( <ConnectStripeAccount /> );

		const connectTestAccountButton = screen.getByText(
			'Create or connect a test account'
		);
		await userEvent.click( connectTestAccountButton );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_create_or_connect_test_account_click',
			{}
		);

		await waitFor( () => {
			expect( window.location.assign ).toHaveBeenCalledWith(
				testOauthUrl
			);
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

	it( 'should disable the live button and show tooltip when SSL is not enabled', async () => {
		// Mock non-SSL protocol
		const protocol = window.location.protocol;
		Object.defineProperty( window, 'location', {
			value: { ...window.location, protocol: 'http:' },
			writable: true,
		} );

		const { container } = render( <ConnectStripeAccount /> );

		// The button should be rendered but disabled
		const connectAccountButton = screen.getByText(
			'Create or connect an account'
		);
		expect( connectAccountButton ).toBeDisabled();

		// Tooltip content should not be visible initially
		expect(
			screen.queryByText(
				'Live mode requires a valid SSL certificate. Please enable SSL on your site to connect a live Stripe account.'
			)
		).not.toBeInTheDocument();

		// Find the tooltip wrapper button (Tooltip wraps content in a button with this class)
		const tooltipWrapper = container.querySelector(
			'.wcstripe-tooltip__content-wrapper'
		);
		expect( tooltipWrapper ).toBeInTheDocument();

		// Click on the tooltip wrapper to trigger tooltip
		await act( async () => {
			await userEvent.click( tooltipWrapper );
		} );

		// Tooltip content should now be visible
		await waitFor( () => {
			expect(
				screen.getByText(
					'Live mode requires a valid SSL certificate. Please enable SSL on your site to connect a live Stripe account.'
				)
			).toBeInTheDocument();
		} );

		// Verify the click did not trigger OAuth flow
		expect( recordEvent ).not.toHaveBeenCalledWith(
			'wcstripe_create_or_connect_account_click',
			{}
		);

		// Restore original protocol
		Object.defineProperty( window, 'location', {
			value: { ...window.location, protocol },
			writable: true,
		} );
	} );
} );
