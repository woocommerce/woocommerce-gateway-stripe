import { dispatch } from '@wordpress/data';
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

export function* saveAccountKeys( accountKeys ) {
	const isDisconnecting =
		! accountKeys.publishable_key && ! accountKeys.test_publishable_key;

	let error = null;
	try {
		yield updateIsSavingAccountKeys( true, null );

		const accountData = yield apiFetch( {
			path: `${ NAMESPACE }/account_keys`,
			method: 'post',
			data: accountKeys,
		} );

		if ( ! accountData?.id ) {
			throw 'Account not Found';
		}

		// When new keys have been set, the user might have entered keys for a new account.
		// So we need to clear the cached account information.
		yield dispatch( STORE_NAME ).invalidateResolutionForStoreSelector(
			'getAccountData'
		);

		yield updateAccountKeysValues( accountKeys );

		yield dispatch( 'core/notices' ).createSuccessNotice(
			isDisconnecting
				? __( 'Account disconnected.', 'woocommerce-gateway-stripe' )
				: __( 'Account keys saved.', 'woocommerce-gateway-stripe' )
		);
	} catch ( e ) {
		error = e;
		yield dispatch( 'core/notices' ).createErrorNotice(
			isDisconnecting
				? __(
						'Error disconnecting account.',
						'woocommerce-gateway-stripe'
				  )
				: __(
						'Error saving account keys.',
						'woocommerce-gateway-stripe'
				  )
		);
	} finally {
		yield updateIsSavingAccountKeys( false, error );
	}

	return error === null;
}
