import { useSelect, useDispatch } from '@wordpress/data';
import { useCallback } from 'react';
import { STORE_NAME } from '../constants';

const EMPTY_ARR = [];

const makeReadOnlySettingsHook = (
	fieldName,
	fieldDefaultValue = false
) => () =>
	useSelect(
		( select ) => {
			const { getSettings } = select( STORE_NAME );

			return getSettings()[ fieldName ] || fieldDefaultValue;
		},
		[ fieldName, fieldDefaultValue ]
	);

const makeSettingsHook = ( fieldName, fieldDefaultValue = false ) => () => {
	const { updateSettingsValues } = useDispatch( STORE_NAME );

	const field = makeReadOnlySettingsHook( fieldName, fieldDefaultValue )();

	const handler = useCallback(
		( value ) =>
			updateSettingsValues( {
				[ fieldName ]: value,
			} ),
		[ updateSettingsValues ]
	);

	return [ field, handler ];
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
export const usePaymentRequestLocations = makeSettingsHook(
	'payment_request_button_locations',
	EMPTY_ARR
);
export const useIsStripeEnabled = makeSettingsHook( 'is_stripe_enabled' );
export const useTitle = makeSettingsHook( 'title', '' );
export const useUpeTitle = makeSettingsHook( 'title_upe', '' );
export const useDescription = makeSettingsHook( 'description', '' );
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
export const useIsUpeEnabled = makeSettingsHook( 'is_upe_enabled' );

export const useIndividualPaymentMethodSettings = makeSettingsHook(
	'individual_payment_method_settings',
	EMPTY_ARR
);

export const useGetAvailablePaymentMethodIds = makeReadOnlySettingsHook(
	'available_payment_method_ids',
	EMPTY_ARR
);

export const useGetSavingError = () => {
	return useSelect( ( select ) => {
		const { getSavingError } = select( STORE_NAME );

		return getSavingError();
	}, [] );
};
