import { dispatch, select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/data-controls';
import { NAMESPACE, STORE_NAME } from '../constants';
import ACTION_TYPES from './action-types';

export function updateAccount( payload ) {
	return {
		type: ACTION_TYPES.SET_ACCOUNT,
		payload,
	};
}

export function updateIsRefreshingAccount( isRefreshing ) {
	return {
		type: ACTION_TYPES.SET_IS_REFRESHING,
		isRefreshing,
	};
}

export function* refreshAccount() {
	try {
		yield updateIsRefreshingAccount( true );

		const activeCapabilitiesBeforeRefresh = select(
			STORE_NAME
		).getAccountCapabilitiesByStatus( 'active' );

		// console.log( activeCapabilitiesBeforeRefresh );

		const data = yield apiFetch( {
			method: 'post',
			path: `${ NAMESPACE }/account/refresh`,
		} );

		yield updateAccount( data );

		const activeCapabilitiesAfterRefresh = select(
			STORE_NAME
		).getAccountCapabilitiesByStatus( 'inactive' );

		// console.log( activeCapabilitiesAfterRefresh );

		const newPaymentMethods = activeCapabilitiesAfterRefresh.filter(
			( paymentMethod ) =>
				! activeCapabilitiesBeforeRefresh.includes( paymentMethod )
		);

		// console.log( newPaymentMethods );

		if ( newPaymentMethods.length ) {
			yield dispatch( 'core/notices' ).createSuccessNotice(
				__(
					'You can now accept payments with X, Y, Z.',
					'woocommerce-gateway-stripe'
				),
				{
					icon: '🚀',
				}
			);
		}
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error updating account data.', 'woocommerce-gateway-stripe' )
		);
	} finally {
		yield updateIsRefreshingAccount( false );
	}
}
