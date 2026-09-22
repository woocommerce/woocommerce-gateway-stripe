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
	const [ highestPage, setHighestPage ] = useState( 1 );

	const [ cursors, setCursors ] = useState( [ null ] );

	const cursor = cursors[ view.page - 1 ] ?? null;

	const { data, hasMore, isLoading, error } = usePayouts( {
		perPage: view.perPage,
		cursor,
	} );

	useEffect( () => {
		if ( hasMore ) {
			const nextPage = view.page + 1;
			if ( nextPage > highestPage ) {
				setHighestPage( nextPage );
			}
		}
	}, [ hasMore, view.page, highestPage ] );

	const paginationInfo = useMemo(
		() => ( {
			totalItems:
				view.perPage * ( view.page - 1 ) +
				data.length +
				( hasMore ? 1 : 0 ),
			totalPages: highestPage,
		} ),
		[ data.length, view.page, view.perPage, hasMore, highestPage ]
	);

	const onChangeView = useCallback(
		( nextView ) => {
			// A different page size invalidates every cursor we collected.
			if ( nextView.perPage !== view.perPage ) {
				setCursors( [ null ] );
				setView( { ...nextView, page: 1 } );
				return;
			}

			setView( nextView );
		},
		[ view.perPage ]
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
