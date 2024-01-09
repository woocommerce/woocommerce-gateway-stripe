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

export function updateIsSavingOrderedPaymentMethodIds(
	isSavingOrderedPaymentMethodIds
) {
	return {
		type: ACTION_TYPES.SET_IS_SAVING_ORDERED_PAYMENT_METHOD_IDS,
		isSavingOrderedPaymentMethodIds,
	};
}

export function updateIsCustomizingPaymentMethod( isCustomizingPaymentMethod ) {
	return {
		type: ACTION_TYPES.SET_IS_CUSTOMIZING_PAYMENT_METHOD,
		isCustomizingPaymentMethod,
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

export function* saveOrderedPaymentMethodIds() {
	try {
		const orderedPaymentMethodIds = select(
			STORE_NAME
		).getOrderedPaymentMethodIds();

		yield updateIsSavingOrderedPaymentMethodIds( true );

		yield apiFetch( {
			path: `${ NAMESPACE }/settings/payment_method_order`,
			method: 'post',
			data: {
				ordered_payment_method_ids: orderedPaymentMethodIds,
			},
		} );

		yield dispatch( 'core/notices' ).createSuccessNotice(
			__( 'Saved changed order.', 'woocommerce-gateway-stripe' )
		);
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error saving changed order.', 'woocommerce-gateway-stripe' )
		);
	} finally {
		yield updateIsSavingOrderedPaymentMethodIds( false );
	}
}

export function* saveIndividualPaymentMethodSettings(
	paymentMethodData = null
) {
	if ( ! paymentMethodData ) {
		return;
	}

	try {
		yield updateIsCustomizingPaymentMethod( true );

		yield apiFetch( {
			path: `${ NAMESPACE }/settings/payment_method`,
			method: 'post',
			data: {
				is_enabled: paymentMethodData.isEnabled,
				payment_method_id: paymentMethodData.method,
				title: paymentMethodData.name,
				description: paymentMethodData.description,
				expiration: paymentMethodData.expiration,
			},
		} );
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error saving payment method.', 'woocommerce-gateway-stripe' )
		);
	} finally {
		yield updateIsCustomizingPaymentMethod( false );
	}
}
