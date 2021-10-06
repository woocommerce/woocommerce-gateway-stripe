import { useSelect, useDispatch } from '@wordpress/data';
import { useCallback } from 'react';
import { STORE_NAME } from '../constants';

const EMPTY_ARR = [];

const makeSettingsHookFromUpdateHandler = (
	fieldName,
	updateHandler,
	fieldDefaultValue = false
) => () => {
	const { updateSettingsValues } = useDispatch( STORE_NAME );

	const field = useSelect(
		( select ) => {
			const { getSettings } = select( STORE_NAME );

			return getSettings()[ fieldName ] || fieldDefaultValue;
		},
		[ fieldName, fieldDefaultValue ]
	);

	const handler = useCallback(
		( v ) => updateHandler( v, updateSettingsValues ),
		[ updateSettingsValues ]
	);

	return [ field, handler ];
};

const makeSettingsHook = ( fieldName, fieldDefaultValue = false ) => {
	const updateHandler = ( v, updateSettingsValues ) =>
		updateSettingsValues( {
			[ fieldName ]: v,
		} );

	return makeSettingsHookFromUpdateHandler(
		fieldName,
		updateHandler,
		fieldDefaultValue
	);
};

const makeReadOnlySettingsHook = ( fieldName, fieldDefaultValue = false ) =>
	makeSettingsHookFromUpdateHandler(
		fieldName,
		() => {},
		fieldDefaultValue
	)[ 0 ];

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

export const useEnabledPaymentMethodIds = makeSettingsHook(
	'enabled_payment_method_ids',
	EMPTY_ARR
);
export const usePaymentRequestEnabledSettings = makeSettingsHook(
	'is_payment_request_enabled'
);
export const usePaymentRequestButtonSize = makeSettingsHook(
	'payment_request_button_size',
	''
);
export const usePaymentRequestButtonType = makeSettingsHook(
	'payment_request_button_type',
	''
);
export const usePaymentRequestButtonTheme = makeSettingsHook(
	'payment_request_button_theme',
	''
);
export const useIsStripeEnabled = makeSettingsHook( 'is_stripe_enabled' );
export const useTestMode = makeSettingsHook( 'is_test_mode_enabled' );
export const useSavedCards = makeSettingsHook( 'is_saved_cards_enabled' );
export const useManualCapture = makeSettingsHook( 'is_manual_capture_enabled' );
export const useSeparateCardForm = makeSettingsHook(
	'is_separate_card_form_enabled'
);
export const useAccountStatementDescriptor = makeSettingsHook(
	'statement_descriptor',
	''
);
export const useIsShortAccountStatementEnabled = makeSettingsHook(
	'is_short_statement_descriptor_enabled'
);
export const useShortAccountStatementDescriptor = makeSettingsHook(
	'short_statement_descriptor',
	''
);
export const useDebugLog = makeSettingsHook( 'is_debug_log_enabled' );

export const usePaymentRequestLocations = makeSettingsHookFromUpdateHandler(
	'payment_request_button_locations',
	( v, updateSettingsValues ) =>
		updateSettingsValues( {
			payment_request_button_locations: [ ...v ],
		} ),
	EMPTY_ARR
);

export const useGetAvailablePaymentMethodIds = makeReadOnlySettingsHook(
	'available_payment_method_ids',
	EMPTY_ARR
);
export const useDevMode = makeReadOnlySettingsHook( 'is_dev_mode_enabled' );

export const useGetSavingError = () => {
	return useSelect( ( select ) => {
		const { getSavingError } = select( STORE_NAME );

		return getSavingError();
	}, [] );
};
