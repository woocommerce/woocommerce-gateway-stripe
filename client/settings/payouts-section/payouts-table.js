import React, { useMemo, useState } from 'react';
import styled from '@emotion/styled';
import { formatAmount } from './format-currency';
import StatusBadge from './status-badge';
import {
	Card,
	CardHeader,
	CardBody,
	Button,
	Flex,
	Notice,
	Spinner,
} from '@wordpress/components';
import { DataViews } from '@wordpress/dataviews/wp';
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import { usePayouts } from 'wcstripe/data/payouts';

const PAGE_SIZE = 25;

const Heading = styled.h2`
	margin: 0;
	font-size: 16px;
	font-weight: 600;
`;

const PaginationBar = styled.div`
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 16px;
`;

const STATUS_ELEMENTS = [
	{ value: 'paid', label: __( 'Paid', 'woocommerce-gateway-stripe' ) },
	{ value: 'pending', label: __( 'Pending', 'woocommerce-gateway-stripe' ) },
	{
		value: 'in_transit',
		label: __( 'In transit', 'woocommerce-gateway-stripe' ),
	},
	{
		value: 'canceled',
		label: __( 'Canceled', 'woocommerce-gateway-stripe' ),
	},
	{ value: 'failed', label: __( 'Failed', 'woocommerce-gateway-stripe' ) },
];

const PayoutsTable = () => {
	const [ cursorStack, setCursorStack ] = useState( [] );
	const [ statusFilter, setStatusFilter ] = useState( null );

	const queryArgs = useMemo(
		() => ( {
			limit: PAGE_SIZE,
			startingAfter: cursorStack[ cursorStack.length - 1 ] || undefined,
			status: statusFilter || undefined,
		} ),
		[ cursorStack, statusFilter ]
	);

	const { payouts, hasMore, isLoading, error } = usePayouts( queryArgs );

	const fields = useMemo(
		() => [
			{
				id: 'amount',
				label: __( 'Amount', 'woocommerce-gateway-stripe' ),
				enableSorting: false,
				render: ( { item } ) =>
					formatAmount( item.amount, item.currency ),
			},
			{
				id: 'currency',
				label: __( 'Currency', 'woocommerce-gateway-stripe' ),
				enableSorting: false,
				render: ( { item } ) => ( item.currency || '' ).toUpperCase(),
			},
			{
				id: 'status',
				label: __( 'Status', 'woocommerce-gateway-stripe' ),
				elements: STATUS_ELEMENTS,
				filterBy: { operators: [ 'is' ] },
				enableSorting: false,
				render: ( { item } ) => <StatusBadge status={ item.status } />,
			},
			{
				id: 'arrival_date',
				label: __( 'Arrival date', 'woocommerce-gateway-stripe' ),
				enableSorting: false,
				render: ( { item } ) =>
					item.arrival_date
						? dateI18n( 'M j, Y', item.arrival_date * 1000 )
						: '',
			},
			{
				id: 'method',
				label: __( 'Method', 'woocommerce-gateway-stripe' ),
				enableSorting: false,
				render: ( { item } ) => item.method || '',
			},
			{
				id: 'description',
				label: __( 'Description', 'woocommerce-gateway-stripe' ),
				enableSorting: false,
				render: ( { item } ) => item.description || item.id,
			},
		],
		[]
	);

	const [ view, setView ] = useState( {
		type: 'table',
		perPage: PAGE_SIZE,
		page: 1,
		fields: [
			'amount',
			'currency',
			'status',
			'arrival_date',
			'method',
			'description',
		],
		filters: [],
	} );

	const onChangeView = ( nextView ) => {
		setView( nextView );

		const statusFilterValue =
			( nextView.filters || [] ).find( ( f ) => f.field === 'status' )
				?.value || null;

		if ( statusFilterValue !== statusFilter ) {
			setStatusFilter( statusFilterValue );
			setCursorStack( [] );
		}
	};

	const onNext = () => {
		const lastId = payouts[ payouts.length - 1 ]?.id;
		if ( lastId ) {
			setCursorStack( ( prev ) => [ ...prev, lastId ] );
		}
	};

	const onPrev = () => {
		setCursorStack( ( prev ) => prev.slice( 0, -1 ) );
	};

	return (
		<Card>
			<CardHeader>
				<Heading>
					{ __( 'Payouts', 'woocommerce-gateway-stripe' ) }
				</Heading>
			</CardHeader>
			<CardBody>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }

				{ isLoading && payouts.length === 0 && <Spinner /> }

				<DataViews
					data={ payouts }
					fields={ fields }
					view={ view }
					onChangeView={ onChangeView }
					getItemId={ ( item ) => item.id }
					paginationInfo={ {
						totalItems: payouts.length,
						totalPages: 1,
					} }
					defaultLayouts={ { table: {} } }
				/>

				<PaginationBar>
					<Flex justify="flex-end" gap="8">
						<Button
							variant="secondary"
							onClick={ onPrev }
							disabled={ cursorStack.length === 0 || isLoading }
						>
							{ __( 'Previous', 'woocommerce-gateway-stripe' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ onNext }
							disabled={ ! hasMore || isLoading }
						>
							{ __( 'Next', 'woocommerce-gateway-stripe' ) }
						</Button>
					</Flex>
				</PaginationBar>
			</CardBody>
		</Card>
	);
};

export default PayoutsTable;
