import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodRequiresCardMethodPill from '..';

describe( 'PaymentMethodRequiresCardMethodPill', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = { is_card_method_enabled: true };
	} );

	it( 'should render the "Enable credit card / debit card" text', () => {
		global.wc_stripe_settings_params = { is_card_method_enabled: false };

		render(
			<PaymentMethodRequiresCardMethodPill
				id="apple_pay_google_pay"
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
				id="apple_pay_google_pay"
				label="Apple Pay / Google Pay"
			/>
		);

		expect( container.firstChild ).toBeNull();
	} );
} );
