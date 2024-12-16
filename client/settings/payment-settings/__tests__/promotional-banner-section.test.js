import { useDispatch } from '@wordpress/data';
import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PromotionalBannerSection from '../promotional-banner-section';
import { useEnabledPaymentMethodIds } from 'wcstripe/data';

jest.mock( '@wordpress/data' );

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
} ) );

jest.mock( 'wcstripe/data', () => ( {
	useEnabledPaymentMethodIds: jest.fn().mockReturnValue( [ [ 'card' ] ] ),
	useTestMode: jest.fn().mockReturnValue( [ false ] ),
} ) );

const noticesDispatch = {
	createErrorNotice: jest.fn(),
	createSuccessNotice: jest.fn(),
};

useDispatch.mockImplementation( ( storeName ) => {
	if ( storeName === 'core/notices' ) {
		return noticesDispatch;
	}

	return {};
} );

const setShowPromotionalBanner = jest.fn();
const setPromotionalBannerType = jest.fn();

describe( 'PromotionalBanner', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = { are_apms_deprecated: false };
	} );

	it( 'dismiss function should be called', () => {
		render(
			<PromotionalBannerSection
				setShowPromotionalBanner={ setShowPromotionalBanner }
				setPromotionalBannerType={ setPromotionalBannerType }
				isConnectedViaOAuth={ true }
			/>
		);

		const dismissButton = screen.getByTestId( 'dismiss' );

		userEvent.click( dismissButton );

		expect( setShowPromotionalBanner ).toHaveBeenCalledWith( false );
	} );

	it( 'Main CTA link for the first version should disable the legacy checkout experience', () => {
		const setIsUpeEnabledMock = jest.fn().mockResolvedValue( true );

		render(
			<PromotionalBannerSection
				setShowPromotionalBanner={ setShowPromotionalBanner }
				setPromotionalBannerType={ setPromotionalBannerType }
				isUpeEnabled={ false }
				setIsUpeEnabled={ setIsUpeEnabledMock }
				isConnectedViaOAuth={ true }
			/>
		);

		userEvent.click( screen.getByText( 'Enable the new checkout' ) );
		expect( setIsUpeEnabledMock ).toHaveBeenCalled();
	} );

	it( 'Display the re-connect promotional surface when OAuth connection is not set', () => {
		render(
			<PromotionalBannerSection
				setShowPromotionalBanner={ setShowPromotionalBanner }
				setPromotionalBannerType={ setPromotionalBannerType }
				isConnectedViaOAuth={ false }
			/>
		);
		expect(
			screen.queryByTestId( 're-connect-account-banner' )
		).toBeInTheDocument();
	} );

	it( 'Display the APM version of the new checkout experience promotional surface when any APM is enabled', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card', 'ideal' ] ] );

		render(
			<PromotionalBannerSection
				setShowPromotionalBanner={ setShowPromotionalBanner }
				setPromotionalBannerType={ setPromotionalBannerType }
				isConnectedViaOAuth={ true }
			/>
		);

		expect(
			screen.queryByTestId( 'new-checkout-apms-banner' )
		).toBeInTheDocument();
	} );
} );
