import { useDispatch } from '@wordpress/data';
import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PromotionalBannerSection from '../promotional-banner-section';

jest.mock( '@wordpress/data' );

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
	it( 'dismiss function should be called', () => {
		render(
			<PromotionalBannerSection
				setShowPromotionalBanner={ setShowPromotionalBanner }
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
				isUpeEnabled={ false }
				setIsUpeEnabled={ setIsUpeEnabledMock }
			/>
		);

		userEvent.click( screen.getByText( 'Enable the new checkout' ) );
		expect( setIsUpeEnabledMock ).toHaveBeenCalled();
	} );
} );
