import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import GeneralSettingsSection from '../general-settings-section';
import {
	useDebugLog,
	useIsStripeEnabled,
	useEnabledPaymentMethodIds,
	useTestMode,
	useUpeTitle,
} from 'wcstripe/data';
import { useAccountKeys } from 'wcstripe/data/account-keys/hooks';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

jest.mock( 'wcstripe/data', () => ( {
	useDebugLog: jest.fn(),
	useIsStripeEnabled: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useTestMode: jest.fn(),
	useUpeTitle: jest.fn().mockReturnValue( [] ),
} ) );

jest.mock( 'wcstripe/data/account-keys/hooks', () => ( {
	useAccountKeys: jest.fn(),
} ) );

jest.mock( '@stripe/stripe-js', () => ( {
	loadStripe: jest.fn(),
} ) );

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

describe( 'GeneralSettingsSection', () => {
	beforeEach( () => {
		useDebugLog.mockReturnValue( [ false, jest.fn() ] );
	} );

	it( 'should enable stripe when stripe checkbox is clicked', () => {
		const setIsStripeEnabledMock = jest.fn();
		const setTestModeMock = jest.fn();
		useIsStripeEnabled.mockReturnValue( [ false, setIsStripeEnabledMock ] );

		useTestMode.mockReturnValue( [ false, setTestModeMock ] );
		useAccountKeys.mockReturnValue( {
			accountKeys: {
				test_publishable_key: 'test_pk',
				test_secret_key: 'test_sk',
				test_webhook_secret: 'test_whs',
			},
		} );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'giropay' ],
			jest.fn(),
		] );

		render( <GeneralSettingsSection /> );

		const enableStripeCheckbox = screen.getByLabelText( 'Enable Stripe' );
		const testModeCheckbox = screen.getByLabelText( 'Enable test mode' );

		expect( enableStripeCheckbox ).not.toBeChecked();
		expect( testModeCheckbox ).not.toBeChecked();

		userEvent.click( enableStripeCheckbox );

		expect( setIsStripeEnabledMock ).toHaveBeenCalledWith( true );

		userEvent.click( testModeCheckbox );

		expect( setTestModeMock ).toHaveBeenCalledWith( true );
	} );

	it( 'should render UPE title field when UPE is enabled', () => {
		useUpeTitle.mockReturnValue( [ 'UPE title', jest.fn() ] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		expect( screen.getByDisplayValue( 'UPE title' ) ).toBeInTheDocument();
	} );

	it( 'should enable debug mode when checkbox is clicked', () => {
		const setIsLoggingCheckedMock = jest.fn();
		useDebugLog.mockReturnValue( [ false, setIsLoggingCheckedMock ] );

		render(
			<UpeToggleContext.Provider value={ { isUpeEnabled: true } }>
				<GeneralSettingsSection />
			</UpeToggleContext.Provider>
		);

		const debugModeCheckbox = screen.getByLabelText( 'Log error messages' );

		expect( screen.getByText( 'Debug mode' ) ).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Log error messages' )
		).not.toBeChecked();

		userEvent.click( debugModeCheckbox );

		expect( setIsLoggingCheckedMock ).toHaveBeenCalledWith( true );
	} );
} );
