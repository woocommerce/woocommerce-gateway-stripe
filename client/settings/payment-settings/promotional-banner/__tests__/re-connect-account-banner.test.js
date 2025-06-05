import { useDispatch } from '@wordpress/data';
import { render } from '@testing-library/react';
import { ReConnectAccountBanner } from 'wcstripe/settings/payment-settings/promotional-banner/re-connect-account-banner';

const noticesDispatch = {
	createErrorNotice: jest.fn(),
};

jest.mock( '@wordpress/data' );

jest.mock( 'wcstripe/tracking', () => ( {
	recordEvent: jest.fn(),
} ) );

describe( 'Reconnect banner', () => {
	const setShowPromotionalBanner = jest.fn();

	beforeEach( () => {
		useDispatch.mockImplementation( ( storeName ) => {
			if ( storeName === 'core/notices' ) {
				return noticesDispatch;
			}

			return {};
		} );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should render the Reconnect promotional banner', () => {
		const { getByText, getByTestId } = render(
			<ReConnectAccountBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		expect(
			getByText( 'Make your store more secure' )
		).toBeInTheDocument();
		expect( getByTestId( 'intro-reconnect' ) ).toBeInTheDocument();
		expect( getByTestId( 're-connect-checkout' ) ).toBeInTheDocument();
	} );
} );
