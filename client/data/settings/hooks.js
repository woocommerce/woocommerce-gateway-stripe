import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../constants';

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

//TODO, these should come from an endpoint/ data store.
export const useEnabledPaymentMethodIds = () => {
	return [ [ 'card', 'sepa_debit' ], () => ( {} ) ];
};
export const useGetAvailablePaymentMethodIds = () => {
	return [ 'card', 'giropay', 'sofort', 'sepa_debit' ];
};
