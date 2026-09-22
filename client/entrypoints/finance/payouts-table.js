import React, { useCallback, useMemo, useState } from 'react';
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

	const [ cursors, setCursors ] = useState( [ null ] );

	const cursor = cursors[ view.page - 1 ] ?? null;

	const { data, hasMore, isLoading, error } = usePayouts( {
		perPage: view.perPage,
		cursor,
	} );

	const paginationInfo = useMemo( () => {
		if ( isLoading ) {
			return {
				totalItems: 0,
				totalPages: 0,
			};
		}

		const totalItems =
			view.perPage * ( view.page - 1 ) +
			data.length +
			( hasMore ? 1 : 0 );
		const totalPages =
			hasMore && data.length === view.perPage ? view.page + 1 : view.page;

		return {
			totalItems,
			totalPages,
		};
	}, [ isLoading, data, view.page, view.perPage, hasMore ] );

	const onChangeView = useCallback(
		( nextView ) => {
			// A different page size invalidates every cursor we collected.
			if ( nextView.perPage !== view.perPage ) {
				setCursors( [ null ] );
				setView( { ...nextView, page: 1 } );
				return;
			}

			if ( nextView.page > view.page ) {
				const lastPayoutId = data[ data.length - 1 ]?.id;

				if ( lastPayoutId ) {
					setCursors( ( prevCursors ) => [
						...prevCursors,
						lastPayoutId,
					] );
				}
			}

			setView( nextView );
		},
		[ view.perPage, view.page, data ]
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
