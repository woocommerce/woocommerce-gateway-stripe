import { useDispatch } from '@wordpress/data';
import { render } from '@testing-library/react';
import { NewCheckoutExperienceBanner } from 'wcstripe/settings/payment-settings/promotional-banner/new-checkout-experience-banner';

const noticesDispatch = {
	createErrorNotice: jest.fn(),
	createSuccessNotice: jest.fn(),
};

jest.mock( '@wordpress/data' );

jest.mock( 'wcstripe/tracking', () => ( {
	recordEvent: jest.fn(),
} ) );

describe( 'New checkout experience banner', () => {
	const setShowPromotionalBanner = jest.fn();

	beforeEach( () => {
		useDispatch.mockImplementation( ( storeName ) => {
			if ( storeName === 'core/notices' ) {
				return noticesDispatch;
			}

			return {};
		} );
		global.wc_stripe_settings_params = { are_apms_deprecated: true };
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should render the New Checkout Experience promotional banner', () => {
		const { getByText, getByTestId } = render(
			<NewCheckoutExperienceBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		expect(
			getByText( 'Boost sales and checkout conversion' )
		).toBeInTheDocument();
		expect( getByTestId( 'intro-new-checkout' ) ).toBeInTheDocument();
		expect( getByTestId( 'enable-the-new-checkout' ) ).toBeInTheDocument();
	} );
} );
