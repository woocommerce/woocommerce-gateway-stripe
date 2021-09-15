/** @format */

/**
 * External dependencies
 */
import { dispatch, select } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ACTION_TYPES from './action-types';
import { NAMESPACE, STORE_NAME } from '../constants';

function updateSettingsValues( payload ) {
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

export function updateIsPaymentRequestEnabled( isEnabled ) {
	return updateSettingsValues( { is_payment_request_enabled: isEnabled } );
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

	return null === error;
}

export function updatePaymentRequestLocations( locations ) {
	return updateSettingsValues( {
		payment_request_enabled_locations: [ ...locations ],
	} );
}

export function updateIsStripeEnabled( isEnabled ) {
	return updateSettingsValues( { is_stripe_enabled: isEnabled } );
}

export function updateIsTestModeEnabled( isEnabled ) {
	return updateSettingsValues( { is_test_mode_enabled: isEnabled } );
}

export function updateIsSavedCardsEnabled( isEnabled ) {
	return updateSettingsValues( { is_saved_cards_enabled: isEnabled } );
}

export function updateIsSeparateCardFormEnabled( isEnabled ) {
	return updateSettingsValues( { is_separate_card_form_enabled: isEnabled } );
}

export function updateIsManualCaptureEnabled( isEnabled ) {
	return updateSettingsValues( { is_manual_capture_enabled: isEnabled } );
}

export function updateAccountStatementDescriptor( accountStatementDescriptor ) {
	return updateSettingsValues( {
		statement_descriptor: accountStatementDescriptor,
	} );
}

export function updateIsShortAccountStatementEnabled( isEnabled ) {
	return updateSettingsValues( { is_short_statement_descriptor_enabled: isEnabled } );
}

export function updateShortAccountStatementDescriptor( shortStatementDescriptor ) {
	return updateSettingsValues( {
		short_statement_descriptor: shortStatementDescriptor,
	} );
}

export function updateIsDebugLogEnabled( isEnabled ) {
	return updateSettingsValues( { is_debug_log_enabled: isEnabled } );
}
