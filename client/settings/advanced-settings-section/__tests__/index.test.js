import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AdvancedSettings from '..';
import apiFetch from '@wordpress/api-fetch';
import {
	useDebugLog,
	useDiagnosticsMode,
	useDiagnosticsCaptureLimit,
	useDiagnosticsCaptureLimitPresets,
	useGetSavingError,
	useSettings,
	useIsOCEnabled,
	useIsAdaptivePricingEnabled,
	useOCLayout,
} from 'wcstripe/data';

jest.mock( 'wcstripe/data', () => ( {
	useDebugLog: jest.fn(),
	useDiagnosticsMode: jest.fn(),
	useDiagnosticsCaptureLimit: jest.fn(),
	useDiagnosticsCaptureLimitPresets: jest.fn(),
	useIsOCEnabled: jest.fn(),
	useIsAdaptivePricingEnabled: jest.fn(),
	useOCLayout: jest.fn(),
	useGetSavingError: jest.fn(),
	useSettings: jest.fn(),
} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn().mockReturnValue( {} ),
} ) );

// DiagnosticsTraces (rendered by DiagnosticsMode) fires a /traces fetch on
// mount. Stub it out at this layer so the existing AdvancedSettings tests
// don't trigger an unwrapped state update warning. The component's own
// behavior is covered by diagnostics-traces.test.js.
jest.mock( '@wordpress/api-fetch', () => jest.fn() );

describe( 'AdvancedSettings', () => {
	beforeEach( () => {
		global.wc_stripe_settings_params = {
			is_cs_available: false,
			is_oc_available: false,
		};

		// Default: no traces stored, so DiagnosticsTraces renders just the
		// empty state.
		apiFetch.mockResolvedValue( { traces: [], count: 0 } );

		useDebugLog.mockReturnValue( [ true, jest.fn() ] );
		useDiagnosticsMode.mockReturnValue( [ false, jest.fn() ] );
		useDiagnosticsCaptureLimit.mockReturnValue( [ 10, jest.fn() ] );
		useDiagnosticsCaptureLimitPresets.mockReturnValue( [ 5, 10, 25, 50 ] );
		useIsOCEnabled.mockReturnValue( [ false, jest.fn() ] );
		useIsAdaptivePricingEnabled.mockReturnValue( [ false, jest.fn() ] );
		useOCLayout.mockReturnValue( [ 'accordion', jest.fn() ] );
		useGetSavingError.mockReturnValue( null );

		// Set `isLoading` to false so `LoadableSettingsSection` can render.
		useSettings.mockReturnValue( { isLoading: false } );
	} );

	it( 'renders the advanced settings section', () => {
		render( <AdvancedSettings /> );

		expect( screen.queryByText( 'Debug mode' ) ).toBeInTheDocument();
	} );

	it( 'should enable debug mode when checkbox is clicked', async () => {
		const setIsLoggingCheckedMock = jest.fn();
		useDebugLog.mockReturnValue( [ false, setIsLoggingCheckedMock ] );

		render( <AdvancedSettings /> );

		const debugModeCheckbox = screen.getByLabelText( 'Log debug messages' );

		expect( screen.getByText( 'Debug mode' ) ).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Log debug messages' )
		).not.toBeChecked();

		await userEvent.click( debugModeCheckbox );

		expect( setIsLoggingCheckedMock ).toHaveBeenCalledWith( true );
	} );

	it( 'should enable diagnostics mode when toggle is clicked', async () => {
		const setIsDiagnosticsCheckedMock = jest.fn();
		useDiagnosticsMode.mockReturnValue( [
			false,
			setIsDiagnosticsCheckedMock,
		] );

		render( <AdvancedSettings /> );

		const diagnosticsToggle = screen.getByLabelText(
			'Capture checkout diagnostics'
		);

		expect(
			screen.getByText( 'Checkout diagnostics' )
		).toBeInTheDocument();
		expect( diagnosticsToggle ).not.toBeChecked();

		await userEvent.click( diagnosticsToggle );

		expect( setIsDiagnosticsCheckedMock ).toHaveBeenCalledWith( true );
	} );

	it( 'should display the Optimized Checkout setting with a warning when the feature is unavailable', () => {
		render( <AdvancedSettings /> );

		expect(
			screen.queryByText(
				'Enable Optimized Checkout Suite (recommended)'
			)
		).toBeInTheDocument();

		// Use `queryAllByText()` and a non-zero length check to handle the
		// notice component including the text in two nodes.
		expect(
			screen.queryAllByText(
				/Optimized Checkout Suite is not currently available/
			)
		).not.toHaveLength( 0 );
	} );

	it( 'should display optimized checkout element setting if the feature flag is enabled', () => {
		global.wc_stripe_settings_params = { is_oc_available: true };

		render( <AdvancedSettings /> );

		expect(
			screen.queryByText(
				'Enable Optimized Checkout Suite (recommended)'
			)
		).toBeInTheDocument();
	} );

	it( 'should display the Optimized Checkout layout and the Adaptive Pricing settings if the Optimized Checkout feature is enabled and checkout sessions available', () => {
		global.wc_stripe_settings_params = {
			is_cs_available: true,
			is_oc_available: true,
		};

		useIsOCEnabled.mockReturnValue( [ true, jest.fn() ] );

		render( <AdvancedSettings /> );

		expect(
			screen.queryByText(
				'Choose between a vertical accordion layout and a horizontal tabs layout to display payment methods.'
			)
		).toBeInTheDocument();
		expect( screen.queryByText( 'Layout' ) ).toBeInTheDocument();

		expect(
			screen.queryByText(
				'Let customers pay in their local currency with Adaptive Pricing'
			)
		).toBeInTheDocument();
	} );
} );
