import React from 'react';
import { screen, render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import GeneralSettingsSection from '../general-settings-section';
import {
	useIsStripeEnabled,
	useEnabledPaymentMethodIds,
	useTestMode,
} from 'wcstripe/data';
import { useAccount } from 'wcstripe/data/account';
import { useAccountKeys } from 'wcstripe/data/account-keys/hooks';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_BANCONTACT,
} from 'wcstripe/stripe-utils/constants';

jest.mock( 'wcstripe/data', () => ( {
	useIsStripeEnabled: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useTestMode: jest.fn(),
	useUpeTitle: jest.fn().mockReturnValue( [] ),
} ) );

jest.mock( 'wcstripe/data/account', () => ( {
	useAccount: jest.fn(),
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
	it( 'should enable stripe when stripe checkbox is clicked', async () => {
		const setIsStripeEnabledMock = jest.fn();
		const setTestModeMock = jest.fn();
		useIsStripeEnabled.mockReturnValue( [ false, setIsStripeEnabledMock ] );

		useTestMode.mockReturnValue( [ false, setTestModeMock ] );
		// A test account must be connected for the "Enable test mode" checkbox to be
		// interactive; otherwise it is disabled to prevent switching into test mode
		// without test keys (see TestModeCheckbox).
		useAccount.mockReturnValue( {
			data: {
				oauth_connections: {
					live: { connected: true },
					test: { connected: true },
				},
			},
		} );
		useAccountKeys.mockReturnValue( {
			accountKeys: {
				test_publishable_key: 'test_pk',
				test_secret_key: 'test_sk',
				test_webhook_secret: 'test_whs',
			},
		} );
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ PAYMENT_METHOD_CARD, PAYMENT_METHOD_BANCONTACT ],
			jest.fn(),
		] );

		render( <GeneralSettingsSection /> );

		const enableStripeCheckbox = screen.getByLabelText( 'Enable Stripe' );
		const testModeCheckbox = screen.getByLabelText( 'Enable test mode' );

		expect( enableStripeCheckbox ).not.toBeChecked();
		expect( testModeCheckbox ).not.toBeChecked();

		await userEvent.click( enableStripeCheckbox );

		expect( setIsStripeEnabledMock ).toHaveBeenCalledWith( true );

		await userEvent.click( testModeCheckbox );

		expect( setTestModeMock ).toHaveBeenCalledWith( true );
	} );
} );
