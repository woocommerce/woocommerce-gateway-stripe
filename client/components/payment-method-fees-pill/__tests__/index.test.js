import React from 'react';
import { render } from '@testing-library/react';
import PaymentMethodFeesPill from '..';

describe( 'PaymentMethodFeesPill', () => {
	it( 'renders the fees for the payment method when the feature flag is enabled', () => {
		global.__PAYMENT_METHOD_FEES_ENABLED = true;
		const { container } = render( <PaymentMethodFeesPill id="giropay" /> );

		expect( container.firstChild ).not.toBeNull();
	} );

	it( 'does not render content when the feature flag is disabled', () => {
		const { container } = render( <PaymentMethodFeesPill id="giropay" /> );

		expect( container.firstChild ).toBeNull();
	} );
} );
