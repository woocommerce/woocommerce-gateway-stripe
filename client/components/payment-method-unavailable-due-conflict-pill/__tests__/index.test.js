import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodUnavailableDueConflictPill from '..';
import { PAYMENT_METHOD_AFFIRM } from 'wcstripe/stripe-utils/constants';

describe( 'PaymentMethodUnavailableDueConflictPill', () => {
	it( 'should render the "Has plugin conflict" text', () => {
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
} );
