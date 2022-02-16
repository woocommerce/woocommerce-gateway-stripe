import { useSelect, useDispatch } from '@wordpress/data';
import React from 'react';
import { screen, render } from '@testing-library/react';
import CustomizationOptionsNotice from '..';
import UpeToggleContext from '../../upe-toggle/context';

jest.mock( '@wordpress/data' );
jest.mock( '@woocommerce/data', () => ( {
	OPTIONS_STORE_NAME: 'wc/admin/options',
} ) );

describe( 'CustomizationOptionsNotice', () => {
	beforeEach( () => {
		useDispatch.mockImplementation( () => ( {
			updateOptions: jest.fn(),
		} ) );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should render the notice when UPE and `wc_show_upe_customization_options_notice` is enabled', () => {
		const selectMock = jest.fn( () => {
			return {
				getOption: () => {
					return 'yes';
				},
				hasFinishedResolution: () => {
					return true;
				},
			};
		} );
		useSelect.mockImplementation( ( callback ) => {
			return callback( selectMock );
		} );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<CustomizationOptionsNotice />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByText( 'Where are the customization options?' )
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				'In the new checkout experience, payment method details are automatically displayed in your customers’ languages so you don’t have to worry about writing them manually.'
			)
		).toBeInTheDocument();
	} );

	it( 'should not render the notice when UPE is disabled but `wc_show_upe_customization_options_notice` is enabled', () => {
		const selectMock = jest.fn( () => {
			return {
				getOption: () => {
					return 'yes';
				},
				hasFinishedResolution: () => {
					return true;
				},
			};
		} );
		useSelect.mockImplementation( ( callback ) => {
			return callback( selectMock );
		} );

		const { container } = render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<CustomizationOptionsNotice />
			</UpeToggleContext.Provider>
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'should not render the notice when UPE is enabled but `wc_show_upe_customization_options_notice` is disabled', () => {
		const selectMock = jest.fn( () => {
			return {
				getOption: () => {
					return 'no';
				},
				hasFinishedResolution: () => {
					return true;
				},
			};
		} );
		useSelect.mockImplementation( ( callback ) => {
			return callback( selectMock );
		} );

		const { container } = render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<CustomizationOptionsNotice />
			</UpeToggleContext.Provider>
		);

		expect( container.firstChild ).toBeNull();
	} );
} );
