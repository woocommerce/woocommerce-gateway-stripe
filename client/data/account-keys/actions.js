import { dispatch, select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/data-controls';
import { NAMESPACE, STORE_NAME } from '../constants';
import ACTION_TYPES from './action-types';

export function updateAccountKeysValues( payload ) {
	return {
		type: ACTION_TYPES.SET_ACCOUNT_KEYS_VALUES,
		payload,
	};
}

export function updateAccountKeys( payload ) {
	return {
		type: ACTION_TYPES.SET_ACCOUNT_KEYS,
		payload,
	};
}

export function updateIsSavingAccountKeys( isSaving, error ) {
	return {
		type: ACTION_TYPES.SET_IS_SAVING_ACCOUNT_KEYS,
		isSaving,
		error,
	};
}

export function* saveAccountKeys() {
	let error = null;
	try {
		const accountKeys = select( STORE_NAME ).getAccountKeys();

		yield updateIsSavingAccountKeys( true, null );

		yield apiFetch( {
			path: `${ NAMESPACE }/account_keys`,
			method: 'post',
			data: accountKeys,
		} );

		// When new keys have been set, the user might have entered keys for a new account.
		// So we need to clear the cached account information.
		yield dispatch( STORE_NAME ).invalidateResolutionForStoreSelector(
			'getAccountData'
		);

		yield dispatch( 'core/notices' ).createSuccessNotice(
			__( 'Account keys saved.', 'woocommerce-gateway-stripe' )
		);
	} catch ( e ) {
		error = e;
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error saving account keys.', 'woocommerce-gateway-stripe' )
		);
	} finally {
		yield updateIsSavingAccountKeys( false, error );
	}

	return error === null;
}
