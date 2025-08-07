import React from 'react';
import { screen, render } from '@testing-library/react';
import OptimizedCheckoutNotice from '..';

describe( 'OptimizedCheckoutNotice', () => {
	it( 'should render the notice when OC is enabled', () => {
		render( <OptimizedCheckoutNotice isOCEnabled={ true } /> );

		const noticeText = screen.queryAllByText(
			"You're using Stripe's Optimized Checkout Suite to dynamically display the most relevant payment methods you've enabled to each customer."
		)?.[ 0 ];
		expect( noticeText ).toBeInTheDocument();
	} );

	it( 'should not render the notice when OC is disabled', () => {
		const { container } = render(
			<OptimizedCheckoutNotice isOCEnabled={ false } />
		);

		expect( container.firstChild ).toBeNull();
	} );
} );
