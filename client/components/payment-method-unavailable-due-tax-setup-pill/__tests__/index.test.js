import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodUnavailableDueTaxSetupPill from '..';
import { PAYMENT_METHOD_AMAZON_PAY } from 'wcstripe/stripe-utils/constants';

describe( 'PaymentMethodUnavailableDueTaxSetupPill', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = { taxes_based_on_billing: false };
	} );

	it( 'should render the "Incompatible tax setup" text', () => {
		global.wc_stripe_settings_params = { taxes_based_on_billing: true };

		render(
			<PaymentMethodUnavailableDueTaxSetupPill
				id={ PAYMENT_METHOD_AMAZON_PAY }
				label="Amazon Pay"
			/>
		);

		expect(
			screen.queryByText( 'Incompatible tax setup' )
		).toBeInTheDocument();
	} );

	it( 'should not render when tax is not based on billing', () => {
		const { container } = render(
			<PaymentMethodUnavailableDueTaxSetupPill
				id={ PAYMENT_METHOD_AMAZON_PAY }
				label="Amazon Pay"
			/>
		);

		expect( container.firstChild ).toBeNull();
	} );
} );
