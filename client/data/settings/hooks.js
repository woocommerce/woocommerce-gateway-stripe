/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
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

export const usePaymentRequestLocations = () => {
	const { updatePaymentRequestLocations } = useDispatch( STORE_NAME );

	const paymentRequestLocations = useSelect( ( select ) => {
		const { getSettings } = select( STORE_NAME );

		return getSettings().payment_request_enabled_locations || EMPTY_ARR;
	} );

	return [ paymentRequestLocations, updatePaymentRequestLocations ];
};

export const useIsStripeEnabled = () => {
	const { updateIsStripeEnabled } = useDispatch( STORE_NAME );

	const isStripeEnabled = useSelect( ( select ) => {
		const { getSettings } = select( STORE_NAME );

		return getSettings().is_stripe_enabled || false;
	}, [] );

	return [ isStripeEnabled, updateIsStripeEnabled ];
};

export const useTestMode = () => {
	const { updateIsTestModeEnabled } = useDispatch( STORE_NAME );

	const isTestModeEnabled = useSelect( ( select ) => {
		const { getSettings } = select( STORE_NAME );

		return getSettings().is_test_mode_enabled || false;
	}, [] );

	return [ isTestModeEnabled, updateIsTestModeEnabled ];
};

export const useGetSavingError = () => {
	return useSelect( ( select ) => {
		const { getSavingError } = select( STORE_NAME );

		return getSavingError();
	}, [] );
};

//TODO, these should come from an endpoint/ data store.
export const useEnabledPaymentMethodIds = () => {
	return [ [ 'card', 'sepa_debit' ], () => ( {} ) ];
};
export const useGetAvailablePaymentMethodIds = () => {
	return [ 'card', 'giropay', 'sofort', 'sepa_debit' ];
};
