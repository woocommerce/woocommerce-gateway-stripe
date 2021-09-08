/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

export const useSettings = () => {
	const { saveSettings } = useDispatch( STORE_NAME );

	return useSelect(
		( select ) => {
			const {
				getSettings,
				hasFinishedResolution,
				isResolving,
				isSavingSettings,
			} = select( STORE_NAME );

			const isLoading =
				isResolving( 'getSettings' ) ||
				! hasFinishedResolution( 'getSettings' );

			return {
				settings: getSettings(),
				isLoading,
				saveSettings,
				isSaving: isSavingSettings(),
			};
		},
		[ saveSettings ]
	);
};

export const usePaymentRequestEnabledSettings = () => {
	const { updateIsPaymentRequestEnabled } = useDispatch( STORE_NAME );

	return useSelect( ( select ) => {
		const { getIsPaymentRequestEnabled } = select( STORE_NAME );

		return [ getIsPaymentRequestEnabled(), updateIsPaymentRequestEnabled ];
	} );
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
