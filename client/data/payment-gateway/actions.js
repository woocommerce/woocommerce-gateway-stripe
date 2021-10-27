import { dispatch, select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/data-controls';
import { getQuery } from '@woocommerce/navigation';
import { NAMESPACE, STORE_NAME } from '../constants';
import ACTION_TYPES from './action-types';

export function updatePaymentGatewayValues( payload ) {
	return {
		type: ACTION_TYPES.SET_PAYMENT_GATEWAY_VALUES,
		payload,
	};
}

export function updatePaymentGateway( data ) {
	return {
		type: ACTION_TYPES.SET_PAYMENT_GATEWAY,
		data,
	};
}

export function updateIsSavingPaymentGateway( isSaving, error ) {
	return {
		type: ACTION_TYPES.SET_IS_SAVING_PAYMENT_GATEWAY,
		isSaving,
		error,
	};
}

export function* savePaymentGateway() {
	let error = null;
	const { section } = getQuery();
	try {
		const settings = select( STORE_NAME ).getPaymentGateway();

		yield updateIsSavingPaymentGateway( true, null );

		yield apiFetch( {
			path: `${ NAMESPACE }/payment-gateway/${ section }`,
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
		yield updateIsSavingPaymentGateway( false, error );
	}

	return error === null;
}
