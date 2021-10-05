import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/data-controls';
import { NAMESPACE } from '../constants';
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

		const data = yield apiFetch( {
			method: 'post',
			path: `${ NAMESPACE }/account/refresh`,
		} );

		yield updateAccount( data );
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error updating account data.', 'woocommerce-gateway-stripe' )
		);
	} finally {
		yield updateIsRefreshingAccount( false );
	}
}
