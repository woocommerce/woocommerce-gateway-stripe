import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodRequiresCardMethodPill from '..';
import { PAYMENT_METHOD_APPLE_PAY_GOOGLE_PAY } from 'wcstripe/stripe-utils/constants';

describe( 'PaymentMethodRequiresCardMethodPill', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = { is_card_method_enabled: true };
	} );

	it( 'should render the "Enable credit card / debit card" text', () => {
		global.wc_stripe_settings_params = { is_card_method_enabled: false };

		render(
			<PaymentMethodRequiresCardMethodPill
				id={ PAYMENT_METHOD_APPLE_PAY_GOOGLE_PAY }
				label="Apple Pay / Google Pay"
			/>
		);

		expect(
			screen.queryByText( 'Enable credit card / debit card' )
		).toBeInTheDocument();
	} );

	it( 'should not render when card is enabled', () => {
		const { container } = render(
			<PaymentMethodRequiresCardMethodPill
				id={ PAYMENT_METHOD_APPLE_PAY_GOOGLE_PAY }
				label="Apple Pay / Google Pay"
			/>
		);

		expect( container.firstChild ).toBeNull();
	} );
} );
