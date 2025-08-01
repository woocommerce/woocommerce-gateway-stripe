import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodRequiredForOCPill from '..';
import { PAYMENT_METHOD_CARD } from 'wcstripe/stripe-utils/constants';
import { useIsOCEnabled } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useIsOCEnabled: jest.fn(),
} ) );

describe( 'PaymentMethodRequiredForOCPill', () => {
	beforeEach( () => {
		useIsOCEnabled.mockReturnValue( [ false, jest.fn() ] );
	} );

	it( 'should render the "Required for the Optimized Checkout Suite" text', () => {
		useIsOCEnabled.mockReturnValue( [ true, jest.fn() ] );
		global.wc_stripe_settings_params = { is_oc_enabled: true };

		render(
			<PaymentMethodRequiredForOCPill
				id={ PAYMENT_METHOD_CARD }
				label="Card"
			/>
		);

		expect( screen.queryByText( 'Required' ) ).toBeInTheDocument();
	} );

	it( 'should not render when OC is not active', () => {
		const { container } = render(
			<PaymentMethodRequiredForOCPill
				id={ PAYMENT_METHOD_CARD }
				label="Card"
			/>
		);

		expect( container.firstChild ).toBeNull();
	} );
} );
