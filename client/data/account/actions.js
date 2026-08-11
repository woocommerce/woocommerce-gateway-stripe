import { NAMESPACE, STORE_NAME } from '../constants';
import PaymentMethodsMap from '../../payment-methods-map';
import { updateSettings } from '../settings/actions';
import ACTION_TYPES from './action-types';
import { dispatch, select } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/data-controls';

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

		const activeCapabilitiesBeforeRefresh =
			select( STORE_NAME ).getAccountCapabilitiesByStatus( 'active' );

		const data = yield apiFetch( {
			method: 'POST',
			path: `${ NAMESPACE }/account/refresh`,
		} );

		yield updateAccount( data );

		// The account refresh can change which payment methods the account has available, so pull
		// the reconciled settings back in. Re-fetch and write them directly rather than invalidating
		// the `getSettings` resolver: invalidation flips `hasFinishedResolution` back to false, which
		// collapses every LoadableSettingsSection on the page into a skeleton until the fetch lands,
		// and it only refetches at all while something is subscribed to `getSettings`.
		const settings = yield apiFetch( { path: `${ NAMESPACE }/settings` } );
		yield updateSettings( settings );

		const activeCapabilitiesAfterRefresh =
			select( STORE_NAME ).getAccountCapabilitiesByStatus( 'active' );

		// Check new payment methods available for account.
		const newPaymentMethods = activeCapabilitiesAfterRefresh.filter(
			( capability ) => {
				const paymentMethodFromCapability =
					capability === 'us_bank_account_ach_payments'
						? 'us_bank_account'
						: capability.replace( '_payments', '' );

				return (
					! activeCapabilitiesBeforeRefresh.includes( capability ) &&
					PaymentMethodsMap[ paymentMethodFromCapability ] !==
						undefined
				);
			}
		);

		// If there are new payment methods available, show a toast informing the user.
		if ( newPaymentMethods.length ) {
			yield dispatch( 'core/notices' ).createSuccessNotice(
				sprintf(
					/* translators: %s: one or more payment method names separated by commas (e.g.: iDEAL, EPS, Klarna, etc). */
					__(
						'You can now accept payments with %s.',
						'woocommerce-gateway-stripe'
					),
					newPaymentMethods
						.map( ( capability ) => {
							const paymentMethodFromCapability =
								capability === 'us_bank_account_ach_payments'
									? 'us_bank_account'
									: capability.replace( '_payments', '' );

							return PaymentMethodsMap[
								paymentMethodFromCapability
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
