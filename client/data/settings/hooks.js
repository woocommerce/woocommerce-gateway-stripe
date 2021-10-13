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

export const useGetAvailablePaymentMethodIds = makeReadOnlySettingsHook(
	'available_payment_method_ids',
	EMPTY_ARR
);

// SEPA
export const useIsStripeSepaEnabled = makeSettingsHook(
	'is_stripe_sepa_enabled'
);
export const useStripeSepaName = makeSettingsHook( 'stripe_sepa_name', '' );
export const useStripeSepaDescription = makeSettingsHook(
	'stripe_sepa_description',
	''
);

// giropay
export const useIsStripeGiropayEnabled = makeSettingsHook(
	'is_stripe_giropay_enabled'
);
export const useStripeGiropayName = makeSettingsHook(
	'stripe_giropay_name',
	''
);
export const useStripeGiropayDescription = makeSettingsHook(
	'stripe_giropay_description',
	''
);

// iDeal
export const useIsStripeIdealEnabled = makeSettingsHook(
	'is_stripe_ideal_enabled'
);
export const useStripeIdealName = makeSettingsHook( 'stripe_ideal_name', '' );
export const useStripeIdealDescription = makeSettingsHook(
	'stripe_ideal_description',
	''
);

// Bancontact
export const useIsStripeBancontactEnabled = makeSettingsHook(
	'is_stripe_bancontact_enabled'
);
export const useStripeBancontactName = makeSettingsHook(
	'stripe_bancontact_name',
	''
);
export const useStripeBancontactDescription = makeSettingsHook(
	'stripe_bancontact_description',
	''
);

// EPS
export const useIsStripeEpsEnabled = makeSettingsHook(
	'is_stripe_eps_enabled'
);
export const useStripeEpsName = makeSettingsHook( 'stripe_eps_name', '' );
export const useStripeEpsDescription = makeSettingsHook(
	'stripe_eps_description',
	''
);

// SOFORT
export const useIsStripeSofortEnabled = makeSettingsHook(
	'is_stripe_sofort_enabled'
);
export const useStripeSofortName = makeSettingsHook( 'stripe_sofort_name', '' );
export const useStripeSofortDescription = makeSettingsHook(
	'stripe_sofort_description',
	''
);

// Alipay
export const useIsStripeAlipayEnabled = makeSettingsHook(
	'is_stripe_alipay_enabled'
);
export const useStripeAlipayName = makeSettingsHook( 'stripe_alipay_name', '' );
export const useStripeAlipayDescription = makeSettingsHook(
	'stripe_alipay_description',
	''
);

// Multibanco
export const useIsStripeMultibancoEnabled = makeSettingsHook(
	'is_stripe_multibanco_enabled'
);
export const useStripeMultibancoName = makeSettingsHook(
	'stripe_multibanco_name',
	''
);
export const useStripeMultibancoDescription = makeSettingsHook(
	'stripe_multibanco_description',
	''
);

export const useGetSavingError = () => {
	return useSelect( ( select ) => {
		const { getSavingError } = select( STORE_NAME );

		return getSavingError();
	}, [] );
};
