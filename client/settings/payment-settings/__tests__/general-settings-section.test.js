import React from 'react';
import { fireEvent, screen, render } from '@testing-library/react';
import GeneralSettingsSection from '../general-settings-section';
import { useIsStripeEnabled, useTestMode } from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useIsStripeEnabled: jest.fn(),
	useTestMode: jest.fn(),
} ) );

describe( 'GeneralSettingsSection', () => {
	beforeEach( () => {
		useIsStripeEnabled.mockReturnValue( [ false, jest.fn() ] );
		useTestMode.mockReturnValue( [ false, jest.fn() ] );
	} );

	it.each( [ [ true ], [ false ] ] )(
		'displays Stripe enabled = %s state from data store',
		( isEnabled ) => {
			useIsStripeEnabled.mockReturnValue( [ isEnabled ] );

			render( <GeneralSettingsSection /> );

			const enableStripeCheckbox = screen.getByLabelText(
				'Enable Stripe'
			);

			let expectation = expect( enableStripeCheckbox );
			if ( ! isEnabled ) {
				expectation = expectation.not;
			}
			expectation.toBeChecked();
		}
	);

	it.each( [ [ true ], [ false ] ] )(
		'updates Stripe enabled state to %s when toggling checkbox',
		( isEnabled ) => {
			const updateIsStripeEnabledMock = jest.fn();
			useIsStripeEnabled.mockReturnValue( [
				isEnabled,
				updateIsStripeEnabledMock,
			] );

			render( <GeneralSettingsSection /> );

			const enableStripeCheckbox = screen.getByLabelText(
				'Enable Stripe'
			);

			fireEvent.click( enableStripeCheckbox );
			expect( updateIsStripeEnabledMock ).toHaveBeenCalledWith(
				! isEnabled
			);
		}
	);

	it.each( [ [ true ], [ false ] ] )(
		'displays test mode enabled = %s state from data store',
		( isEnabled ) => {
			useTestMode.mockReturnValue( [ isEnabled ] );

			render( <GeneralSettingsSection /> );

			const enableTestModeCheckbox = screen.getByLabelText(
				'Enable test mode'
			);

			let expectation = expect( enableTestModeCheckbox );
			if ( ! isEnabled ) {
				expectation = expectation.not;
			}
			expectation.toBeChecked();
		}
	);

	it.each( [ [ true ], [ false ] ] )(
		'updates test mode enabled state to %s when toggling checkbox',
		( isEnabled ) => {
			const updateTestModeEnabledMock = jest.fn();
			useTestMode.mockReturnValue( [
				isEnabled,
				updateTestModeEnabledMock,
			] );

			render( <GeneralSettingsSection /> );

			const enableTestModeCheckbox = screen.getByLabelText(
				'Enable test mode'
			);

			fireEvent.click( enableTestModeCheckbox );
			expect( updateTestModeEnabledMock ).toHaveBeenCalledWith(
				! isEnabled
			);
		}
	);
} );
