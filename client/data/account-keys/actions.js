import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/data-controls';
import { NAMESPACE } from '../constants';
import { refreshAccount } from '../account/actions';
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

export function updateIsTestingAccountKeys( isTesting ) {
	return {
		type: ACTION_TYPES.SET_IS_TESTING_ACCOUNT_KEYS,
		isTesting,
	};
}

export function updateIsValidAccountKeys( isValid ) {
	return {
		type: ACTION_TYPES.SET_IS_VALID_KEYS,
		isValid,
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
			method: 'POST',
			data: accountKeys,
		} );

		if ( ! accountData?.id ) {
			throw 'Account not Found';
		}

		// refresh account data after keys are updated in the database
		yield refreshAccount();

		// update keys on the state
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

export function* testAccountKeys( { live, publishable, secret } ) {
	let error = null;
	try {
		yield updateIsTestingAccountKeys( true );
		yield apiFetch( {
			path: `${ NAMESPACE }/account_keys/test`,
			method: 'POST',
			data: {
				live_mode: live,
				publishable,
				secret,
			},
		} );
	} catch ( e ) {
		error = e;
	} finally {
		yield updateIsTestingAccountKeys( false );
	}

	return error === null;
}
