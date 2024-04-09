import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PromotionalBannerSection from '../promotional-banner-section';

describe( 'PromotionalBanner', () => {
	it( 'dismiss function should be called', () => {
		const setShowPromotionalBanner = jest.fn();
		render(
			<PromotionalBannerSection
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);

		const dismissButton = screen.getByTestId( 'dismiss' );

		userEvent.click( dismissButton );

		expect( setShowPromotionalBanner ).toHaveBeenCalledWith( false );
	} );

	it( '"Learn more" link should contain the Stripe URL', () => {
		render( <PromotionalBannerSection /> );

		const learnMoreLink = screen.getByTestId( 'learn-more' );

		expect( learnMoreLink ).toHaveAttribute(
			'href',
			'https://stripe.com/en-br/capital'
		);
	} );
} );
