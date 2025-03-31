import React from 'react';
import { screen, render } from '@testing-library/react';
import ExpressCheckoutInaccurateTaxesPill from '..';

describe( 'ExpressCheckoutInaccurateTaxesPill', () => {
	it( 'should render the "Tax-based limitations" text', () => {
		render( <ExpressCheckoutInaccurateTaxesPill taxBasedOn="billing" /> );

		expect(
			screen.queryByText( 'Tax-based limitations' )
		).toBeInTheDocument();
	} );

	it( 'should not render when taxes are not based on billing address', () => {
		const { container } = render(
			<ExpressCheckoutInaccurateTaxesPill taxBasedOn="shipping" />
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'should not render when taxes are based on billing address', () => {
		render( <ExpressCheckoutInaccurateTaxesPill taxBasedOn="billing" /> );

		expect(
			screen.queryByText( 'Tax-based limitations' )
		).toBeInTheDocument();
	} );
} );
