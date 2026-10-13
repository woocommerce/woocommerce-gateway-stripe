import { useEffect, useMemo, useRef, useState } from 'react';
import { PAYOUTS_PATH } from './constants';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { NAMESPACE } from 'wcstripe/data/constants';

const DATE_PRECISION = {
	DAY: 'day',
	EXACT: 'exact',
};

/**
 * Build a date range filter based on two Date values.
 *
 * @param {string} precision The precision of the date values. Should be a value in DATE_PRECISION, 'day' or 'exact'.
 * @param {Date}   startDate The start date value.
 * @param {Date}   endDate   The end date value.
 *
 * @return {Object} Query arguments for the date range filter.
 */
const getDateFilterRange = ( precision, startDate, endDate ) => {
	const startOfDay = new Date( startDate );
	const endOfDay = new Date( endDate );

	if ( precision === DATE_PRECISION.DAY ) {
		startOfDay.setHours( 0, 0, 0, 0 );
		endOfDay.setDate( endOfDay.getDate() + 1 );
		endOfDay.setHours( 0, 0, 0, 0 );
	}

	return {
		gte: Math.floor( startOfDay.getTime() / 1000 ),
		lt: Math.floor( endOfDay.getTime() / 1000 ),
	};
};

/**
 * Maps a DataView date filter to the corresponding query arguments.
 *
 * @param {string} operator        The DataView filter operator, e.g. 'between', 'after', 'before'.
 * @param {string} precision       The precision of the date values. Should be a value in DATE_PRECISION, 'day' or 'exact'.
 * @param {Date}   dateValue       The date value from the filter.
 * @param {?Date}  secondDateValue The second date value from the filter, for 'between' filters.
 *
 * @return {?Object} Query arguments, or null if the filter is not supported.
 */
const mapDateFilter = (
	operator,
	precision,
	dateValue,
	secondDateValue = null
) => {
	if ( operator === 'after' ) {
		if ( precision === DATE_PRECISION.DAY ) {
			dateValue.setDate( dateValue.getDate() + 1 );
		}
		return {
			gt: Math.floor( dateValue.getTime() / 1000 ),
		};
	}

	if ( operator === 'before' ) {
		if ( precision === DATE_PRECISION.DAY ) {
			dateValue.setDate( dateValue.getDate() - 1 );
		}
		return {
			lt: Math.floor( dateValue.getTime() / 1000 ),
		};
	}

	if ( operator === 'afterInc' ) {
		return {
			gte: Math.floor( dateValue.getTime() / 1000 ),
		};
	}

	if ( operator === 'beforeInc' ) {
		return {
			lte: Math.floor( dateValue.getTime() / 1000 ),
		};
	}

	if ( operator === 'on' ) {
		return getDateFilterRange( precision, dateValue, dateValue );
	}

	if ( operator === 'between' ) {
		return getDateFilterRange( precision, dateValue, secondDateValue );
	}

	return null;
};

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
 * @param {?Array}  args.filters Dataview filters to apply to the query. May be null when no filters have been specified.
 * @return {{data: Array, hasMore: boolean, isLoading: boolean, error: ?string, cursor: (?string|undefined), perPage: (number|undefined)}} Fetch state.
 */
const usePayouts = ( { perPage, cursor, filters } ) => {
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

	const filterArgs = useMemo( () => {
		const args = {};

		if (
			! Array.isArray( filters ) ||
			filters.some( ( filter ) => filter.value === undefined )
		) {
			return args;
		}

		filters.forEach( ( filter ) => {
			if ( filter.field === 'status' && filter.operator === 'is' ) {
				args.status = filter.value.toLowerCase();
			}
			if (
				filter.field === 'arrival_date' ||
				filter.field === 'created'
			) {
				// arrival_date is a date, while created is a full timestamp.
				const precision =
					filter.field === 'arrival_date'
						? DATE_PRECISION.DAY
						: DATE_PRECISION.EXACT;

				let firstDateValue = null;
				let secondDateValue = null;
				if ( filter.operator === 'between' ) {
					firstDateValue = new Date( filter.value[ 0 ] );
					secondDateValue = new Date( filter.value[ 1 ] );
				} else {
					firstDateValue = new Date( filter.value );
				}
				const mappedFilter = mapDateFilter(
					filter.operator,
					precision,
					firstDateValue,
					secondDateValue
				);
				if ( mappedFilter ) {
					if ( ! args[ filter.field ] ) {
						args[ filter.field ] = {};
					}
					args[ filter.field ] = mappedFilter;
				}
			}
		} );

		return args;
	}, [ filters ] );

	const queryArgs = useMemo( () => {
		return {
			limit: perPage,
			...( cursor ? { starting_after: cursor } : {} ),
			...filterArgs,
		};
	}, [ perPage, cursor, filterArgs ] );

	useEffect( () => {
		// Don't make a request if we have any incomplete filter definitions.
		if (
			Array.isArray( filters ) &&
			filters.some( ( filter ) => filter.value === undefined )
		) {
			return;
		}

		const requestId = ++requestIdRef.current;
		let cancelled = false;

		setState( ( previous ) => ( {
			...previous,
			isLoading: true,
			error: null,
		} ) );

		apiFetch( {
			path: addQueryArgs( `${ NAMESPACE }${ PAYOUTS_PATH }`, queryArgs ),
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
	}, [ cursor, filters, perPage, queryArgs ] );

	return state;
};

export default usePayouts;
