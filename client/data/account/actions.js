import { dispatch, select } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/data-controls';
import { NAMESPACE, STORE_NAME } from '../constants';
import PaymentMethodsMap from '../../payment-methods-map';
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

		const data = yield apiFetch( {
			method: 'POST',
			path: `${ NAMESPACE }/account/refresh`,
		} );

		yield updateAccount( data );

		const activeCapabilitiesAfterRefresh = select(
			STORE_NAME
		).getAccountCapabilitiesByStatus( 'active' );

		// Check new payment methods available for account.
		const newPaymentMethods = activeCapabilitiesAfterRefresh.filter(
			( paymentMethod ) =>
				! activeCapabilitiesBeforeRefresh.includes( paymentMethod ) &&
				PaymentMethodsMap[
					paymentMethod.replace( '_payments', '' )
				] !== undefined
		);

		// If there are new payment methods available, show a toast informing the user.
		if ( newPaymentMethods.length ) {
			yield dispatch( 'core/notices' ).createSuccessNotice(
				sprintf(
					/* translators: %s: one or more payment method names separated by commas (e.g.: giropay, EPS, Sofort, etc). */
					__(
						'You can now accept payments with %s.',
						'woocommerce-gateway-stripe'
					),
					newPaymentMethods
						.map( ( method ) => {
							return PaymentMethodsMap[
								method.replace( '_payments', '' )
							].label;
						} )
						.join( ', ' )
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
