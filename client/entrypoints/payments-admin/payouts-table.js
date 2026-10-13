import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { DEFAULT_PAYOUTS_VIEW, PER_PAGE_SIZES } from './constants';
import fields from './payouts-fields';
import usePayouts from './use-payouts';
// The `/wp` build inlines @wordpress/components, ui, element and private-apis,
// externalizing only wp-data, wp-date, wp-hooks and wp-i18n. The default entry
// would instead externalize @wordpress/components, handing DataViews whatever
// version the running WordPress ships — and it needs the `Menu` private API
// from components@38, far newer than the plugin's minimum supported WP.
import { DataViews } from '@wordpress/dataviews/wp';
import { __ } from '@wordpress/i18n';
import InlineNotice from 'wcstripe/components/inline-notice';

const EmptyState = () => (
	<p>{ __( 'No payouts found.', 'woocommerce-gateway-stripe' ) }</p>
);

const PayoutsTable = () => {
	const [ view, setView ] = useState( DEFAULT_PAYOUTS_VIEW );

	// Track the cursors for the pages that have been loaded.
	// Note that we only set this state from fetched data.
	const [ cursors, setCursors ] = useState( [ null ] );

	const cursor = cursors[ view.page - 1 ] ?? null;

	const {
		data,
		hasMore,
		isLoading,
		error,
		cursor: loadedCursor,
		perPage: loadedPerPage,
	} = usePayouts( {
		perPage: view.perPage,
		cursor,
	} );

	const isCurrentPageLoaded =
		! isLoading &&
		! error &&
		loadedCursor === cursor &&
		loadedPerPage === view.perPage;

	useEffect( () => {
		if ( ! isCurrentPageLoaded ) {
			return;
		}

		const nextCursor = hasMore ? data[ data.length - 1 ]?.id : undefined;

		setCursors( ( prevCursors ) => {
			if ( prevCursors[ view.page ] === nextCursor ) {
				return prevCursors;
			}

			// Keep only the cursors for the pages up to the current page.
			// This ensures that we can pick up new data from Stripe.
			const nextCursors = prevCursors.slice( 0, view.page );
			if ( nextCursor ) {
				nextCursors[ view.page ] = nextCursor;
			}
			return nextCursors;
		} );
	}, [ isCurrentPageLoaded, data, hasMore, view.page ] );

	const paginationInfo = useMemo( () => {
		if ( isLoading ) {
			return {
				totalItems: 0,
				totalPages: 0,
			};
		}

		const totalPages = Math.max( cursors.length, view.page );

		// Stripe doesn't return totals, so this is a moving value
		// that we update as we get more rows.
		const totalItems =
			view.page === totalPages
				? view.perPage * ( view.page - 1 ) + data.length
				: view.perPage * ( totalPages - 1 ) + 1;

		return {
			totalItems,
			totalPages,
		};
	}, [ isLoading, cursors.length, data.length, view.page, view.perPage ] );

	const onChangeView = useCallback(
		( nextView ) => {
			// A different page size invalidates every cursor we collected.
			if ( nextView.perPage !== view.perPage ) {
				setCursors( [ null ] );
				setView( { ...nextView, page: 1 } );
				return;
			}

			// Pages past the last known cursor cannot be fetched directly.
			setView( {
				...nextView,
				page: Math.min( nextView.page, cursors.length ),
			} );
		},
		[ view.perPage, cursors.length ]
	);

	const showTable = ! error || data.length > 0;

	return (
		<>
			{ error && (
				<InlineNotice status="error" isDismissible={ false }>
					{ error }
				</InlineNotice>
			) }

			{ showTable && (
				<DataViews
					data={ data }
					fields={ fields }
					view={ view }
					onChangeView={ onChangeView }
					getItemId={ ( item ) => item.id }
					isLoading={ isLoading }
					paginationInfo={ paginationInfo }
					defaultLayouts={ { table: {} } }
					config={ { perPageSizes: PER_PAGE_SIZES } }
					empty={ <EmptyState /> }
					search={ false }
				/>
			) }
		</>
	);
};

export default PayoutsTable;
