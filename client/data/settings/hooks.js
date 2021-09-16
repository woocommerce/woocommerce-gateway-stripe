/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useCallback } from 'react';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

const EMPTY_ARR = [];

const makeReadWritePairHookWithUpdateCallback = (
	fieldName,
	updateActionCb,
	fieldDefaultValue = false
) => () => {
	const { updateSettingsValues } = useDispatch( STORE_NAME );

	const field = useSelect( ( select ) => {
		const { getSettings } = select( STORE_NAME );

		return getSettings()[ fieldName ] || fieldDefaultValue;
	}, [] );

	const action = ( v ) => updateActionCb( v, updateSettingsValues );

	return [ field, action ];
};

const makeReadWritePairHook = ( fieldName, fieldDefaultValue = false ) => {
	const action = ( v, updateSettingsValues ) =>
		updateSettingsValues( {
			[ fieldName ]: v,
		} );

	return makeReadWritePairHookWithUpdateCallback(
		fieldName,
		action,
		fieldDefaultValue
	);
};

export const useSettings = () => {
	const { saveSettings } = useDispatch( STORE_NAME );

	const settings = useSelect( ( select ) => {
		const { getSettings } = select( STORE_NAME );

		return getSettings();
	}, [] );

	const isLoading = useSelect( ( select ) => {
		const { hasFinishedResolution, isResolving } = select( STORE_NAME );

		return (
			isResolving( 'getSettings' ) ||
			! hasFinishedResolution( 'getSettings' )
		);
	}, [] );

	const isSaving = useSelect( ( select ) => {
		const { isSavingSettings } = select( STORE_NAME );

		return isSavingSettings();
	}, [] );

	return { settings, isLoading, isSaving, saveSettings };
};

export const usePaymentRequestEnabledSettings = makeReadWritePairHook(
	'is_payment_request_enabled'
);
export const useIsStripeEnabled = makeReadWritePairHook( 'is_stripe_enabled' );
export const useTestMode = makeReadWritePairHook( 'is_test_mode_enabled' );
export const useSavedCards = makeReadWritePairHook( 'is_saved_cards_enabled' );
export const useManualCapture = makeReadWritePairHook(
	'is_manual_capture_enabled'
);
export const useSeparateCardForm = makeReadWritePairHook(
	'is_separate_card_form_enabled'
);
export const useAccountStatementDescriptor = makeReadWritePairHook(
	'statement_descriptor',
	''
);
export const useIsShortAccountStatementEnabled = makeReadWritePairHook(
	'is_short_statement_descriptor_enabled'
);
export const useShortAccountStatementDescriptor = makeReadWritePairHook(
	'short_statement_descriptor',
	''
);
export const useDebugLog = makeReadWritePairHook( 'is_debug_log_enabled' );

export const usePaymentRequestLocations = makeReadWritePairHookWithUpdateCallback(
	'payment_request_button_locations',
	( v, updateSettingsValues ) =>
		updateSettingsValues( {
			payment_request_button_locations: [ ...v ],
		} ),
	EMPTY_ARR
);

export const useGetSavingError = () => {
	return useSelect( ( select ) => {
		const { getSavingError } = select( STORE_NAME );

		return getSavingError();
	}, [] );
};

export const usePaymentRequestButtonType = () => {
	const { updateSettingsValues } = useDispatch( STORE_NAME );

	const type = useSelect( ( select ) => {
		const { getSettings } = select( STORE_NAME );

		return getSettings().payment_request_button_type || '';
	} );

	const handler = useCallback(
		( value ) =>
			updateSettingsValues( {
				payment_request_button_type: value,
			} ),
		[ updateSettingsValues ]
	);

	return [ type, handler ];
};

export const usePaymentRequestButtonSize = () => {
	const { updateSettingsValues } = useDispatch( STORE_NAME );

	const size = useSelect( ( select ) => {
		const { getSettings } = select( STORE_NAME );

		return getSettings().payment_request_button_size || '';
	} );

	const handler = useCallback(
		( value ) =>
			updateSettingsValues( {
				payment_request_button_size: value,
			} ),
		[ updateSettingsValues ]
	);

	return [ size, handler ];
};

export const usePaymentRequestButtonTheme = () => {
	const { updateSettingsValues } = useDispatch( STORE_NAME );

	const size = useSelect( ( select ) => {
		const { getSettings } = select( STORE_NAME );

		return getSettings().payment_request_button_theme || '';
	} );

	const handler = useCallback(
		( value ) =>
			updateSettingsValues( {
				payment_request_button_theme: value,
			} ),
		[ updateSettingsValues ]
	);

	return [ size, handler ];
};

//TODO, these should come from an endpoint/ data store.
export const useEnabledPaymentMethodIds = () => {
	return [ [ 'card', 'sepa_debit' ], () => ( {} ) ];
};
export const useGetAvailablePaymentMethodIds = () => {
	return [ 'card', 'giropay', 'sofort', 'sepa_debit' ];
};
