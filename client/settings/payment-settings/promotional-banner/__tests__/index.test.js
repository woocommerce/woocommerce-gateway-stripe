import { useDispatch } from '@wordpress/data';
import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PromotionalBanner from '..';
import { useEnabledPaymentMethodIds } from 'wcstripe/data';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_IDEAL,
} from 'wcstripe/stripe-utils/constants';
import {
	NEW_CHECKOUT_EXPERIENCE_APMS_BANNER,
	NEW_CHECKOUT_EXPERIENCE_BANNER,
	RECONNECT_BANNER,
} from 'wcstripe/settings/payment-settings/constants';

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

describe( 'PromotionalBanner', () => {
	// Keep the original function.
	const reload = window.location.reload;
	beforeEach( () => {
		Object.defineProperty( window, 'location', {
			value: { reload: jest.fn() },
		} );
		global.wc_stripe_settings_params = { are_apms_deprecated: false };
	} );

	afterEach( () => {
		// Set the original function back to keep further tests working as expected.
		Object.defineProperty( window, 'location', {
			value: { reload },
		} );
		jest.restoreAllMocks();
	} );

	it( 'dismiss function should be called', async () => {
		render(
			<PromotionalBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
				isConnectedViaOAuth={ true }
				promotionalBannerType={ NEW_CHECKOUT_EXPERIENCE_BANNER }
			/>
		);

		const dismissButton = screen.getByTestId( 'dismiss' );

		await userEvent.click( dismissButton );

		expect( setShowPromotionalBanner ).toHaveBeenCalledWith( false );
	} );

	it( 'Main CTA link for the first version should disable the legacy checkout experience', async () => {
		const setIsUpeEnabledMock = jest.fn().mockResolvedValue( true );

		render(
			<PromotionalBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
				isUpeEnabled={ false }
				setIsUpeEnabled={ setIsUpeEnabledMock }
				isConnectedViaOAuth={ true }
				promotionalBannerType={ NEW_CHECKOUT_EXPERIENCE_BANNER }
			/>
		);

		await userEvent.click( screen.getByText( 'Enable the new checkout' ) );
		expect( setIsUpeEnabledMock ).toHaveBeenCalled();
	} );

	it( 'Display the re-connect promotional surface when OAuth connection is not set', () => {
		render(
			<PromotionalBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
				isConnectedViaOAuth={ false }
				promotionalBannerType={ RECONNECT_BANNER }
			/>
		);
		expect(
			screen.queryByTestId( 're-connect-account-banner' )
		).toBeInTheDocument();
	} );

	it( 'Display the APM version of the new checkout experience promotional surface when any APM is enabled', () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD, PAYMENT_METHOD_IDEAL ],
		] );

		render(
			<PromotionalBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
				isConnectedViaOAuth={ true }
				promotionalBannerType={ NEW_CHECKOUT_EXPERIENCE_APMS_BANNER }
			/>
		);

		expect(
			screen.queryByTestId( 'new-checkout-apms-banner' )
		).toBeInTheDocument();
	} );
} );
