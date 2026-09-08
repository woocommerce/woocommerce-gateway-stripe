import { useEffect, useRef, useState } from 'react';
import { PAYOUTS_PATH } from './constants';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { NAMESPACE } from 'wcstripe/data/constants';

/**
 * Fetches a page of Stripe payouts.
 *
 * Stripe pages by cursor, so callers pass the id of the last row of the
 * previous page rather than an offset.
 *
 * @param {Object}  args         Query arguments.
 * @param {number}  args.perPage Rows per page; the endpoint caps this at 100.
 * @param {?string} args.cursor  Payout ID to start after, or null for the first page.
 * @return {{data: Array, hasMore: boolean, isLoading: boolean, error: ?string}} Fetch state.
 */
const usePayouts = ( { perPage, cursor } ) => {
	const [ state, setState ] = useState( {
		data: [],
		hasMore: false,
		isLoading: true,
		error: null,
	} );

	// Cursor paging means requests are not interchangeable: a slow response for
	// an earlier page must not overwrite a later one. Only the newest request
	// is allowed to commit.
	const requestIdRef = useRef( 0 );

	useEffect( () => {
		const requestId = ++requestIdRef.current;
		let cancelled = false;

		setState( ( previous ) => ( {
			...previous,
			isLoading: true,
			error: null,
		} ) );

		apiFetch( {
			path: addQueryArgs( `${ NAMESPACE }${ PAYOUTS_PATH }`, {
				limit: perPage,
				...( cursor ? { starting_after: cursor } : {} ),
			} ),
		} )
			.then( ( response ) => {
				if ( cancelled || requestId !== requestIdRef.current ) {
					return;
				}

				setState( {
					data: response?.data ?? [],
					hasMore: Boolean( response?.has_more ),
					isLoading: false,
					error: null,
				} );
			} )
			.catch( ( error ) => {
				if ( cancelled || requestId !== requestIdRef.current ) {
					return;
				}

				// Keep the last good page on screen so a transient failure does
				// not blank the table out from under the reader.
				setState( ( previous ) => ( {
					...previous,
					isLoading: false,
					error: error?.message ?? null,
				} ) );
			} );

		return () => {
			cancelled = true;
		};
	}, [ perPage, cursor ] );

	return state;
};

export default usePayouts;
