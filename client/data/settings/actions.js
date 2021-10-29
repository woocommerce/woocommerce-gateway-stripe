import { dispatch, select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/data-controls';
import { NAMESPACE, STORE_NAME } from '../constants';
import ACTION_TYPES from './action-types';

export function updateSettingsValues( payload ) {
	return {
		type: ACTION_TYPES.SET_SETTINGS_VALUES,
		payload,
	};
}

export function updateSettings( data ) {
	return {
		type: ACTION_TYPES.SET_SETTINGS,
		data,
	};
}

export function updateIsSavingSettings( isSaving, error ) {
	return {
		type: ACTION_TYPES.SET_IS_SAVING_SETTINGS,
		isSaving,
		error,
	};
}

export function* saveSettings() {
	let error = null;
	try {
		const settings = select( STORE_NAME ).getSettings();

		yield updateIsSavingSettings( true, null );

		yield apiFetch( {
			path: `${ NAMESPACE }/settings`,
			method: 'post',
			data: settings,
		} );

		// when the settings are saved, the "test mode" flag might have changed.
		// In that case, we also need to fetch the "account" data again, to make sure we have it up to date.
		yield dispatch( STORE_NAME ).invalidateResolutionForStoreSelector(
			'getAccountData'
		);

		yield dispatch( 'core/notices' ).createSuccessNotice(
			__( 'Settings saved.', 'woocommerce-gateway-stripe' )
		);
	} catch ( e ) {
		error = e;
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error saving settings.', 'woocommerce-gateway-stripe' )
		);
	} finally {
		yield updateIsSavingSettings( false, error );
	}

	return error === null;
}
