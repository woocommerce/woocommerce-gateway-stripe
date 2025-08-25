import { useDispatch } from '@wordpress/data';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { NewCheckoutExperienceAPMsBanner } from 'wcstripe/settings/payment-settings/promotional-banner/new-checkout-experience-apms-banner';

const noticesDispatch = {
	createErrorNotice: jest.fn(),
	createSuccessNotice: jest.fn(),
};

jest.mock( '@wordpress/data' );

jest.mock( 'wcstripe/tracking', () => ( {
	recordEvent: jest.fn(),
} ) );

describe( 'New checkout experience APMs banner', () => {
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

	it( 'should render the New Checkout Experience APMs promotional banner', () => {
		const { getByText } = render(
			<NewCheckoutExperienceAPMsBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		expect(
			getByText(
				'Enable the new Stripe checkout to continue accepting non-card payments'
			)
		).toBeInTheDocument();
		expect(
			getByText(
				/To continue accepting non-card payments, you must enable the new checkout experience or remove non-card payment methods from your checkout to avoid payment disruptions./
			)
		).toBeInTheDocument();
		expect( getByText( 'Enable the new checkout' ) ).toBeInTheDocument();
	} );
	it( 'should dismiss on button click', async () => {
		const { getByText } = render(
			<NewCheckoutExperienceAPMsBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		const dismissButton = getByText( 'Dismiss' );

		await userEvent.click( dismissButton );

		expect( setShowPromotionalBanner ).toHaveBeenCalledWith( false );
	} );
} );
