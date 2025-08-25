import { useDispatch } from '@wordpress/data';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
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
	it( 'should record event on button click', async () => {
		// Keep the original function at hand.
		const assign = window.location.assign;

		Object.defineProperty( window, 'location', {
			value: { assign: jest.fn() },
		} );

		const oauthUrl = 'http://example.com/test-oauth';
		const { getByText } = render(
			<ReConnectAccountBanner testOauthUrl={ oauthUrl } />
		);
		const reconnectButton = getByText( 'Re-authenticate' );
		await userEvent.click( reconnectButton );

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcstripe_create_or_connect_test_account_click',
			{}
		);

		expect( window.location.assign ).toHaveBeenCalledWith( oauthUrl );

		// Set the original function back to keep further tests working as expected.
		Object.defineProperty( window, 'location', {
			value: { assign },
		} );
	} );
	it( 'should create error notice when test oauth URL is invalid, and test mode is enabled', async () => {
		const oauthUrl = 'http://example.com/test-oauth';
		const { getByText } = render(
			<ReConnectAccountBanner
				testOauthUrl={ null }
				oauthUrl={ oauthUrl }
			/>
		);
		const reconnectButton = getByText( 'Re-authenticate' );
		await userEvent.click( reconnectButton );

		expect( noticesDispatch.createErrorNotice ).toHaveBeenCalledWith(
			'There was an error. Please reload the page and try again.'
		);
	} );
	it( 'should create error notice when live oauth URL is invalid, and test mode is disabled', async () => {
		useTestMode.mockReturnValue( [ false, jest.fn() ] );

		const oauthUrl = 'http://example.com/test-oauth';
		const { getByText } = render(
			<ReConnectAccountBanner
				testOauthUrl={ oauthUrl }
				oauthUrl={ null }
			/>
		);
		const reconnectButton = getByText( 'Re-authenticate' );
		await userEvent.click( reconnectButton );

		expect( noticesDispatch.createErrorNotice ).toHaveBeenCalledWith(
			'There was an error. Please reload the page and try again.'
		);
	} );
	it( 'should create error notice when both oauth URLs are invalid', async () => {
		const { getByText } = render(
			<ReConnectAccountBanner testOauthUrl={ null } oauthUrl={ null } />
		);
		const reconnectButton = getByText( 'Re-authenticate' );
		await userEvent.click( reconnectButton );

		expect( noticesDispatch.createErrorNotice ).toHaveBeenCalledWith(
			'There was an error. Please reload the page and try again.'
		);
	} );
} );
