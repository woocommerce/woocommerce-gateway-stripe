import React, { useCallback, useMemo, useState } from 'react';
import { DEFAULT_PAYOUTS_VIEW, PER_PAGE_SIZES } from './constants';
import fields from './payouts-fields';
import usePayouts from './use-payouts';
import { Button, Flex, FlexItem } from '@wordpress/components';
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

	// DataViews requires paginationInfo, but nothing renders it here: the
	// endpoint reports no totals, and DataViews.Pagination is deliberately not
	// mounted below. Real paging is the Previous/Next pair.
	const paginationInfo = useMemo(
		() => ( { totalItems: data.length, totalPages: 1 } ),
		[ data.length ]
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

	const goToNextPage = useCallback( () => {
		const lastItem = data[ data.length - 1 ];

		if ( ! lastItem ) {
			return;
		}

		setCursors( ( previous ) => {
			const next = previous.slice( 0, view.page );
			next.push( lastItem.id );
			return next;
		} );
		setView( ( previous ) => ( { ...previous, page: previous.page + 1 } ) );
	}, [ data, view.page ] );

	const goToPreviousPage = useCallback( () => {
		setView( ( previous ) => ( {
			...previous,
			page: Math.max( 1, previous.page - 1 ),
		} ) );
	}, [] );

	const isFirstPage = view.page <= 1;
	const showTable = ! error || data.length > 0;

	return (
		<>
			{ error && (
				<InlineNotice status="error" isDismissible={ false }>
					{ error }
				</InlineNotice>
			) }

			{ showTable && (
				<>
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
					>
						<DataViews.Layout />
					</DataViews>

					<Flex
						className="wc-stripe-payouts__pagination"
						justify="flex-end"
						gap={ 2 }
					>
						<FlexItem>
							<Button
								variant="secondary"
								onClick={ goToPreviousPage }
								disabled={ isFirstPage || isLoading }
								__next40pxDefaultSize
							>
								{ __(
									'Previous',
									'woocommerce-gateway-stripe'
								) }
							</Button>
						</FlexItem>
						<FlexItem>
							<Button
								variant="secondary"
								onClick={ goToNextPage }
								disabled={ ! hasMore || isLoading }
								__next40pxDefaultSize
							>
								{ __( 'Next', 'woocommerce-gateway-stripe' ) }
							</Button>
						</FlexItem>
					</Flex>
				</>
			) }
		</>
	);
};

export default PayoutsTable;
