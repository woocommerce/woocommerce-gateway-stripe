import { useEffect, useRef, useState } from 'react';
import { PAYOUTS_PATH } from './constants';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { NAMESPACE } from 'wcstripe/data/constants';

/**
 * Fetches a page of Stripe payouts.
 *
 * Stripe pages by cursor, so callers pass the id of the last row of the
 * previous page rather than an offset.
 *
 * We return `cursor` and `perPage` in the result to allow callers to know which
 * inputs were used to fetch the current data.
 *
 * @param {Object}  args         Query arguments.
 * @param {number}  args.perPage Rows per page; the endpoint caps this at 100.
 * @param {?string} args.cursor  Payout ID to start after, or null for the first page.
 * @return {{data: Array, hasMore: boolean, isLoading: boolean, error: ?string, cursor: (?string|undefined), perPage: (number|undefined)}} Fetch state.
 */
const usePayouts = ( { perPage, cursor } ) => {
	const [ state, setState ] = useState( {
		data: [],
		hasMore: false,
		isLoading: true,
		error: null,
	} );

	// Cursor paging means requests are not interchangeable: a slow response for
	// an earlier page must not overwrite a later one. Only the most recent request
	// is allowed to update the data we return.
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
					cursor,
					perPage,
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
					error:
						error?.message ??
						__(
							'Unable to load payouts.',
							'woocommerce-gateway-stripe'
						),
				} ) );
			} );

		return () => {
			cancelled = true;
		};
	}, [ perPage, cursor ] );

	return state;
};

export default usePayouts;
