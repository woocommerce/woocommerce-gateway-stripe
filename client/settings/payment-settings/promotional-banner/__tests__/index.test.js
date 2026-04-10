import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PromotionalBanner from '..';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import {
	OC_PROMOTION_BANNER,
	RECONNECT_BANNER,
} from 'wcstripe/settings/payment-settings/constants';

jest.mock( '@wordpress/data' );

jest.mock( '@wordpress/api-fetch' );

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
} ) );

jest.mock( 'wcstripe/data', () => ( {
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
		apiFetch.mockImplementation(
			jest.fn( () => Promise.resolve( { data: {} } ) )
		);
	} );

	afterEach( () => {
		// Set the original function back to keep further tests working as expected.
		Object.defineProperty( window, 'location', {
			value: { reload },
		} );
		jest.restoreAllMocks();
	} );

	it( 'dismiss function should be called', async () => {
		const dismissBannerMock = jest.fn( () =>
			Promise.resolve( { data: {} } )
		);
		apiFetch.mockImplementation( dismissBannerMock );

		render(
			<PromotionalBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
				isConnectedViaOAuth={ true }
				promotionalBannerType={ OC_PROMOTION_BANNER }
			/>
		);

		const dismissButton = screen.getByTestId( 'dismiss' );

		await userEvent.click( dismissButton );

		expect( setShowPromotionalBanner ).toHaveBeenCalledWith( false );
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
} );
