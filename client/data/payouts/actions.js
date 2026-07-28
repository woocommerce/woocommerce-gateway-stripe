import { NAMESPACE } from '../constants';
import ACTION_TYPES from './action-types';
import { addQueryArgs } from '@wordpress/url';
import { apiFetch } from '@wordpress/data-controls';

export function updateBalance( payload ) {
	return {
		type: ACTION_TYPES.SET_BALANCE,
		payload,
	};
}

export function updateIsLoadingBalance( isLoading ) {
	return {
		type: ACTION_TYPES.SET_IS_LOADING_BALANCE,
		isLoading,
	};
}

export function updateBalanceError( error ) {
	return {
		type: ACTION_TYPES.SET_BALANCE_ERROR,
		error,
	};
}

export function updatePayouts( payload ) {
	return {
		type: ACTION_TYPES.SET_PAYOUTS,
		payload,
	};
}

export function updateIsLoadingPayouts( isLoading ) {
	return {
		type: ACTION_TYPES.SET_IS_LOADING_PAYOUTS,
		isLoading,
	};
}

export function updatePayoutsError( error ) {
	return {
		type: ACTION_TYPES.SET_PAYOUTS_ERROR,
		error,
	};
}

export function* refreshBalance() {
	yield updateIsLoadingBalance( true );
	yield updateBalanceError( null );

	try {
		const data = yield apiFetch( {
			path: `${ NAMESPACE }/payouts/balance`,
		} );
		yield updateBalance( data );
	} catch ( e ) {
		yield updateBalanceError( e?.message || 'error' );
	} finally {
		yield updateIsLoadingBalance( false );
	}
}

export function* refreshPayouts( args = {} ) {
	yield updateIsLoadingPayouts( true );
	yield updatePayoutsError( null );

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
		yield updatePayoutsError( e?.message || 'error' );
	} finally {
		yield updateIsLoadingPayouts( false );
	}
}
