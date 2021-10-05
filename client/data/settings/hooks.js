import { useSelect, useDispatch } from '@wordpress/data';
import { useCallback } from 'react';
import { STORE_NAME } from '../constants';

const EMPTY_ARR = [];

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

export const usePaymentRequestEnabledSettings = () => {
	const { updateIsPaymentRequestEnabled } = useDispatch( STORE_NAME );

	const isPaymentRequestEnabled = useSelect( ( select ) => {
		const { getSettings } = select( STORE_NAME );

		return getSettings().is_payment_request_enabled || false;
	}, [] );

	return [ isPaymentRequestEnabled, updateIsPaymentRequestEnabled ];
};

export const useGetSavingError = () => {
	return useSelect( ( select ) => {
		const { getSavingError } = select( STORE_NAME );

		return getSavingError();
	}, [] );
};

export const usePaymentRequestLocations = () => {
	const { updateSettingsValues } = useDispatch( STORE_NAME );

	const locations = useSelect( ( select ) => {
		const { getSettings } = select( STORE_NAME );

		return getSettings().payment_request_button_locations || EMPTY_ARR;
	} );

	const handler = useCallback(
		( values ) =>
			updateSettingsValues( {
				payment_request_button_locations: values,
			} ),
		[ updateSettingsValues ]
	);

	return [ locations, handler ];
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

export const useEnabledPaymentMethodIds = () => {
	const { updateSettingsValues } = useDispatch( STORE_NAME );

	const methods = useSelect( ( select ) => {
		const { getSettings } = select( STORE_NAME );

		return getSettings().enabled_payment_method_ids || EMPTY_ARR;
	} );

	const handler = useCallback(
		( value ) =>
			updateSettingsValues( {
				enabled_payment_method_ids: value,
			} ),
		[ updateSettingsValues ]
	);

	return [ methods, handler ];
};

export const useTestMode = () => {
	const { updateSettingsValues } = useDispatch( STORE_NAME );

	const methods = useSelect( ( select ) => {
		const { getSettings } = select( STORE_NAME );

		return getSettings().is_test_mode_enabled || false;
	} );

	const handler = useCallback(
		( value ) =>
			updateSettingsValues( {
				is_test_mode_enabled: value,
			} ),
		[ updateSettingsValues ]
	);

	return [ methods, handler ];
};

export const useGetAvailablePaymentMethodIds = () =>
	useSelect( ( select ) => {
		const { getSettings } = select( STORE_NAME );

		return getSettings().available_payment_method_ids || EMPTY_ARR;
	} );
