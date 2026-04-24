import { NAMESPACE } from '../constants';
import {
	updateBalance,
	updateBalanceError,
	updatePayouts,
	updatePayoutsError,
} from './actions';
import { addQueryArgs } from '@wordpress/url';
import { apiFetch } from '@wordpress/data-controls';
import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

export function* getBalance() {
	try {
		const data = yield apiFetch( {
			path: `${ NAMESPACE }/payouts/balance`,
		} );
		yield updateBalance( data );
	} catch ( e ) {
		const message =
			e?.message ||
			__( 'Error retrieving balance.', 'woocommerce-gateway-stripe' );
		yield updateBalanceError( message );
		yield dispatch( 'core/notices' ).createErrorNotice( message );
	}
}

export function* getPayouts( args = {} ) {
	try {
		const query = {};
		if ( args.limit ) {
			query.limit = args.limit;
		}
		if ( args.startingAfter ) {
			query.starting_after = args.startingAfter;
		}
		if ( args.status ) {
			query.status = args.status;
		}

		const data = yield apiFetch( {
			path: addQueryArgs( `${ NAMESPACE }/payouts`, query ),
		} );
		yield updatePayouts( data );
	} catch ( e ) {
		const message =
			e?.message ||
			__( 'Error retrieving payouts.', 'woocommerce-gateway-stripe' );
		yield updatePayoutsError( message );
		yield dispatch( 'core/notices' ).createErrorNotice( message );
	}
}
