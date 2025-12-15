import React from 'react';
import { screen, render } from '@testing-library/react';
import PaymentMethodDeprecationPill from '..';

describe( 'PaymentMethodDeprecationPill', () => {
	it( 'should render', () => {
		render( <PaymentMethodDeprecationPill /> );

		expect( screen.queryByText( 'Deprecated' ) ).toBeInTheDocument();
	} );
} );
