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

export const useGetOrderedPaymentMethodIds = () => {
	const { saveOrderedPaymentMethodIds } = useDispatch( STORE_NAME );

	const { updateSettingsValues } = useDispatch( STORE_NAME );

	const orderedPaymentMethodIds = makeReadOnlySettingsHook(
		'ordered_payment_method_ids',
		EMPTY_ARR
	)();

	const isSaving = useSelect( ( select ) => {
		const { isSavingOrderedPaymentMethodIds } = select( STORE_NAME );

		return isSavingOrderedPaymentMethodIds();
	}, [] );

	const setOrderedPaymentMethodIds = useCallback(
		( value ) =>
			updateSettingsValues( {
				ordered_payment_method_ids: value,
			} ),
		[ updateSettingsValues ]
	);

	return {
		orderedPaymentMethodIds,
		isSaving,
		setOrderedPaymentMethodIds,
		saveOrderedPaymentMethodIds,
	};
};

export const useCustomizePaymentMethodSettings = () => {
	const {
		saveIndividualPaymentMethodSettings,
		updateSettingsValues,
	} = useDispatch( STORE_NAME );

	const individualPaymentMethodSettings = useSelect( ( select ) => {
		const { getIndividualPaymentMethodSettings } = select( STORE_NAME );

		return getIndividualPaymentMethodSettings();
	}, [] );

	const isCustomizing = useSelect( ( select ) => {
		const { isCustomizingPaymentMethod } = select( STORE_NAME );

		return isCustomizingPaymentMethod();
	}, [] );

	const customizePaymentMethod = useCallback(
		async ( method, isEnabled, data ) => {
			updateSettingsValues( {
				individual_payment_method_settings: data,
			} );
			await saveIndividualPaymentMethodSettings( {
				isEnabled,
				method,
				name: data[ method ].name,
				description: data[ method ].description,
				expiration: data[ method ].expiration,
			} );
		},
		[ saveIndividualPaymentMethodSettings, updateSettingsValues ]
	);

	return {
		individualPaymentMethodSettings,
		isCustomizing,
		customizePaymentMethod,
	};
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
export const useTestMode = makeSettingsHook( 'is_test_mode_enabled' );
export const useSavedCards = makeSettingsHook( 'is_saved_cards_enabled' );
export const useManualCapture = makeSettingsHook( 'is_manual_capture_enabled' );
export const useSeparateCardForm = makeSettingsHook(
	'is_separate_card_form_enabled'
);
export const useIsShortAccountStatementEnabled = makeSettingsHook(
	'is_short_statement_descriptor_enabled'
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
