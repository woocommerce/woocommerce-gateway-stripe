import React from 'react';
import { screen, render } from '@testing-library/react';
import OptimizedCheckoutNotice from '..';
import OCToggleContext from 'wcstripe/settings/oc-toggle/context';

jest.mock( '@wordpress/api-fetch' );

describe( 'OptimizedCheckoutNotice', () => {
	it( 'should render the notice when UPE is enabled', () => {
		render(
			<OCToggleContext.Provider value={ { isOCEnabled: true } }>
				<OptimizedCheckoutNotice />
			</OCToggleContext.Provider>
		);

		const noticeText = screen.queryAllByText(
			"You're using Stripe's Optimized Checkout Suite to dynamically display the most relevant payment methods you've enabled to each customer."
		)?.[ 0 ];
		expect( noticeText ).toBeInTheDocument();
	} );

	it( 'should not render the notice when OC is disabled', () => {
		const { container } = render(
			<OCToggleContext.Provider value={ { isOCEnabled: false } }>
				<OptimizedCheckoutNotice />
			</OCToggleContext.Provider>
		);

		expect( container.firstChild ).toBeNull();
	} );
} );
