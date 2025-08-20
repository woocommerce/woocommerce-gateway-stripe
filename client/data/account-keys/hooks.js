import { useSelect, useDispatch } from '@wordpress/data';
import { useCallback } from 'react';
import { STORE_NAME } from '../constants';

export const useAccountKeys = () => {
	const {
		saveAccountKeys,
		updateAccountKeys,
		updateIsTestingAccountKeys,
		updateIsValidAccountKeys,
		testAccountKeys,
		configureWebhooks,
	} = useDispatch( STORE_NAME );

	const accountKeys = useSelect( ( select ) => {
		const { getAccountKeys } = select( STORE_NAME );

		return getAccountKeys();
	}, [] );

	const isTesting = useSelect( ( select ) => {
		const { getIsTestingAccountKeys } = select( STORE_NAME );

		return getIsTestingAccountKeys();
	}, [] );

	const isValid = useSelect( ( select ) => {
		const { getIsValidAccountKeys } = select( STORE_NAME );

		return getIsValidAccountKeys();
	}, [] );

	const isLoading = useSelect( ( select ) => {
		const { hasFinishedResolution, isResolving } = select( STORE_NAME );

		return (
			isResolving( 'getAccountKeys' ) ||
			! hasFinishedResolution( 'getAccountKeys' )
		);
	}, [] );

	const isSaving = useSelect( ( select ) => {
		const { isSavingAccountKeys } = select( STORE_NAME );

		return isSavingAccountKeys();
	}, [] );

	const isConfiguring = useSelect( ( select ) => {
		const { isConfiguringWebhooks } = select( STORE_NAME );

		return isConfiguringWebhooks();
	}, [] );

	return {
		accountKeys,
		isLoading,
		isSaving,
		isTesting,
		isValid,
		updateAccountKeys,
		updateIsTestingAccountKeys,
		updateIsValidAccountKeys,
		saveAccountKeys,
		testAccountKeys,
		configureWebhooks,
		isConfiguring,
	};
};

export const useGetSavingError = () => {
	return useSelect( ( select ) => {
		const { getSavingError } = select( STORE_NAME );

		return getSavingError();
	}, [] );
};

const makeAccountKeysValueHook = ( attribute ) => () => {
	const { updateAccountKeysValues } = useDispatch( STORE_NAME );

	const value = useSelect( ( select ) => {
		const { getAccountKeys } = select( STORE_NAME );

		return getAccountKeys()[ attribute ] || '';
	} );

	const handler = useCallback(
		( newValue ) =>
			updateAccountKeysValues( {
				[ attribute ]: newValue,
			} ),
		[ updateAccountKeysValues ]
	);

	return [ value, handler ];
};

export const useAccountKeysPublishableKey = makeAccountKeysValueHook(
	'publishable_key'
);

export const useAccountKeysSecretKey = makeAccountKeysValueHook( 'secret_key' );

export const useAccountKeysWebhookSecret = makeAccountKeysValueHook(
	'webhook_secret'
);

export const useAccountKeysTestPublishableKey = makeAccountKeysValueHook(
	'test_publishable_key'
);

export const useAccountKeysTestSecretKey = makeAccountKeysValueHook(
	'test_secret_key'
);

export const useAccountKeysTestWebhookSecret = makeAccountKeysValueHook(
	'test_webhook_secret'
);

export const useAccountKeysTestWebhookURL = makeAccountKeysValueHook(
	'test_webhook_url'
);

export const useAccountKeysWebhookURL = makeAccountKeysValueHook(
	'webhook_url'
);
