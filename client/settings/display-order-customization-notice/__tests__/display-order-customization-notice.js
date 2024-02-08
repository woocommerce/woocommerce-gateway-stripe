import apiFetch from '@wordpress/api-fetch';
import React from 'react';
import { screen, render, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import DisplayOrderCustomizationNotice from '..';
import UpeToggleContext from '../../upe-toggle/context';

jest.mock( '@wordpress/api-fetch' );

describe( 'DisplayOrderCustomizationNotice', () => {
	const globalValues = global.wc_stripe_settings_params;
	beforeEach( () => {
		apiFetch.mockImplementation(
			jest.fn( () => Promise.resolve( { data: {} } ) )
		);
		global.wc_stripe_settings_params = {
			...globalValues,
			show_customization_notice: true,
		};
	} );

	afterEach( () => {
		jest.clearAllMocks();
		global.wc_stripe_settings_params = globalValues;
	} );

	it( 'should render the notice when UPE is disabled and `show_customization_notice` is true', () => {
		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<DisplayOrderCustomizationNotice />
			</UpeToggleContext.Provider>
		);

		const noticeText = screen.queryAllByText(
			"Customize the display order of Stripe payment methods for customers at checkout. This customization occurs within the plugin and won't affect the order in relation to other installed payment providers."
		)?.[ 0 ];
		expect( noticeText ).toBeInTheDocument();
	} );

	it( 'should make an API call to dismiss the banner on button click', () => {
		const dismissNoticeMock = jest.fn( () =>
			Promise.resolve( { data: {} } )
		);
		apiFetch.mockImplementation( dismissNoticeMock );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<DisplayOrderCustomizationNotice />
			</UpeToggleContext.Provider>
		);

		const dismissButton = screen.queryByRole( 'button', {
			'aria-label': 'Dismiss the notice',
		} );
		expect( dismissButton ).toBeInTheDocument();
		act( () => {
			userEvent.click( dismissButton );
		} );
		expect( dismissNoticeMock ).toHaveBeenCalled();
	} );

	it( 'should not render the notice when UPE is enabled', () => {
		const { container } = render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<DisplayOrderCustomizationNotice />
			</UpeToggleContext.Provider>
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'should not render the notice when `show_customization_notice` is false', () => {
		global.wc_stripe_settings_params = {
			...globalValues,
			show_customization_notice: false,
		};

		const { container } = render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<DisplayOrderCustomizationNotice />
			</UpeToggleContext.Provider>
		);

		expect( container.firstChild ).toBeNull();
	} );
} );
