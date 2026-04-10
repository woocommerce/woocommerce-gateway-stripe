import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodRequiresCardMethodPill from '..';
import { PAYMENT_METHOD_APPLE_PAY_GOOGLE_PAY } from 'wcstripe/stripe-utils/constants';

describe( 'PaymentMethodRequiresCardMethodPill', () => {
	it( 'should render the "Enable credit card / debit card" text', () => {
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
} );
