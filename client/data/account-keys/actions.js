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

		if ( ! isDisconnecting && ! accountData?.id ) {
			throw 'Account not found.';
		}

		// refresh account data after keys are updated in the database
		yield refreshAccount();

		// update keys on the state
		yield updateAccountKeysValues( accountKeys );

		yield dispatch( 'core/notices' ).createSuccessNotice(
			isDisconnecting
				? __( 'Account disconnected.', 'woocommerce-gateway-stripe' )
				: __( 'Account connected.', 'woocommerce-gateway-stripe' )
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
						'Error connecting account.',
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

export function updateIsConfiguringWebhooks( isProcessing ) {
	return {
		type: ACTION_TYPES.SET_IS_CONFIGURING_WEBHOOKS,
		isProcessing,
	};
}

export function* configureWebhooks( { live, secret } ) {
	let error = null;

	try {
		yield updateIsConfiguringWebhooks( true );

		// Send the request to Configure the Webhook.
		const response = yield apiFetch( {
			path: `${ NAMESPACE }/account_keys/configure_webhooks`,
			method: 'POST',
			data: {
				live_mode: live,
				secret,
			},
		} );

		const webhookValues = live
			? {
					webhook_secret: response.webhookSecret,
					webhook_url: response.webhookURL,
			  }
			: {
					test_webhook_secret: response.webhookSecret,
					test_webhook_url: response.webhookURL,
			  };

		yield updateAccountKeysValues( webhookValues );

		yield dispatch( 'core/notices' ).createSuccessNotice(
			__(
				'Webhooks have been setup successfully.',
				'woocommerce-gateway-stripe'
			)
		);
	} catch ( e ) {
		error = e;
		yield dispatch( 'core/notices' ).createErrorNotice( error.message );
	} finally {
		yield updateIsConfiguringWebhooks( false );
	}

	return error === null;
}
