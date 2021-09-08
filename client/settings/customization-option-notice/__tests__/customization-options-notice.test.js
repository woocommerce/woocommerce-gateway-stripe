/**
 * External dependencies
 */
import React from 'react';
import { screen, render } from '@testing-library/react';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import CustomizationOptionNotice from '..';
import UpeToggleContext from '../../upe-toggle/context';

jest.mock( '@wordpress/data' );

jest.mock( '@wordpress/a11y', () => ( {
	...jest.requireActual( '@wordpress/a11y' ),
	speak: jest.fn(),
} ) );

describe( 'CustomizationOptionNotice', () => {
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
				<CustomizationOptionNotice />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByText( 'Where are customization options?' )
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

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: false } }>
				<CustomizationOptionNotice />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByText( 'Where are customization options?' )
		).not.toBeInTheDocument();
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

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<CustomizationOptionNotice />
			</UpeToggleContext.Provider>
		);

		expect(
			screen.queryByText( 'Where are customization options?' )
		).not.toBeInTheDocument();
	} );
} );
