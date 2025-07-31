import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodUnavailableDueConflictPill from '..';
import { PAYMENT_METHOD_AFFIRM } from 'wcstripe/stripe-utils/constants';

describe( 'PaymentMethodUnavailableDueConflictPill', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = { has_affirm_gateway_plugin: false };
	} );

	it( 'should render the "Has plugin conflict" text', () => {
		global.wc_stripe_settings_params = { has_affirm_gateway_plugin: true };

		render(
			<PaymentMethodUnavailableDueConflictPill
				id={ PAYMENT_METHOD_AFFIRM }
				label="Affirm"
			/>
		);

		expect(
			screen.queryByText( 'Has plugin conflict' )
		).toBeInTheDocument();
	} );

	it( 'should not render when other extensions are not active', () => {
		const { container } = render(
			<PaymentMethodUnavailableDueConflictPill
				id={ PAYMENT_METHOD_AFFIRM }
				label="Affirm"
			/>
		);

		expect( container.firstChild ).toBeNull();
	} );
} );
