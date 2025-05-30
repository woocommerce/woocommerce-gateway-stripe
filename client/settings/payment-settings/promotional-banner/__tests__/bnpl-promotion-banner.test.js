import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BNPLPromotionBanner } from '../bnpl-promotion-banner';

describe( 'BNPL promotional banner', () => {
	const setShowPromotionalBanner = jest.fn();

	it( 'should render the BNPL promotional banner', () => {
		const { getByText, getByTestId } = render(
			<BNPLPromotionBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		expect(
			getByText( 'Offer more ways to pay with Buy Now, Pay Later' )
		).toBeInTheDocument();
		expect( getByTestId( 'intro-bnpl' ) ).toBeInTheDocument();
		expect( getByText( '*Source: Stripe 2024' ) ).toBeInTheDocument();
	} );

	it( 'should call setShowPromotionalBanner with false when the banner is dismissed', () => {
		const { getByTestId } = render(
			<BNPLPromotionBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		const dismissButton = getByTestId( 'dismiss' );
		userEvent.click( dismissButton );
		expect( setShowPromotionalBanner ).toHaveBeenCalledWith( false );
	} );

	it( 'link should contain the correct attributes', async () => {
		const { getByText } = render(
			<BNPLPromotionBanner
				setShowPromotionalBanner={ setShowPromotionalBanner }
			/>
		);
		const link = getByText( 'Learn more' );

		expect( link ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/stripe/setup-and-configuration/additional-payment-methods/'
		);
	} );
} );
