import React from 'react';
import { screen, render, act } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import userEvent from '@testing-library/user-event';
import OptimizedCheckoutNotice from '..';

jest.mock( '@wordpress/api-fetch' );

describe( 'OptimizedCheckoutNotice', () => {
	const globalValues = global.wc_stripe_settings_params;
	beforeEach( () => {
		apiFetch.mockImplementation(
			jest.fn( () => Promise.resolve( { data: {} } ) )
		);
		global.wc_stripe_settings_params = {
			...globalValues,
			show_optimized_checkout_notice: true,
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
		global.wc_stripe_settings_params = globalValues;
	} );

	it( 'should render the notice when OC is enabled', () => {
		render( <OptimizedCheckoutNotice isOCEnabled={ true } /> );

		const noticeText = screen.queryAllByText(
			"You're using Stripe's Optimized Checkout Suite to dynamically display the most relevant payment methods you've enabled to each customer."
		)?.[ 0 ];
		expect( noticeText ).toBeInTheDocument();
	} );

	it( 'should make an API call to dismiss the banner on button click', async () => {
		const dismissNoticeMock = jest.fn( () =>
			Promise.resolve( { data: {} } )
		);
		apiFetch.mockImplementation( dismissNoticeMock );

		render( <OptimizedCheckoutNotice isOCEnabled={ true } /> );

		const dismissButton = screen.queryByRole( 'button', {
			'aria-label': 'Dismiss the notice',
		} );
		expect( dismissButton ).toBeInTheDocument();
		await act( async () => {
			await userEvent.click( dismissButton );
		} );
		expect( dismissNoticeMock ).toHaveBeenCalled();
	} );

	it( 'should not render the notice when OC is disabled', () => {
		const { container } = render(
			<OptimizedCheckoutNotice isOCEnabled={ false } />
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'should not render the notice when `show_optimized_checkout_notice` is false', () => {
		global.wc_stripe_settings_params = {
			...globalValues,
			show_optimized_checkout_notice: false,
		};

		const { container } = render(
			<OptimizedCheckoutNotice isOCEnabled={ true } />
		);

		expect( container.firstChild ).toBeNull();
	} );
} );
