import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodUnavailableDueTaxSetupPill from '..';
import { PAYMENT_METHOD_AMAZON_PAY } from 'wcstripe/stripe-utils/constants';

describe( 'PaymentMethodUnavailableDueTaxSetupPill', () => {
	it( 'should render the "Incompatible tax setup" text', () => {
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
} );
