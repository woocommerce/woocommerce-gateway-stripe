import React, { useState, useEffect, useCallback } from 'react';
import styled from '@emotion/styled';
import { check, close, help, pending, warning } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
	Flex,
	Icon,
	Notice,
} from '@wordpress/components';
import { dispatch } from '@wordpress/data';
import Pill from 'wcstripe/components/pill';

const HISTORY_ROW_LIMIT = 10;

const DetailsTable = styled.table`
	border-collapse: collapse;
	margin: 16px 0 12px;
	width: 100%;

	th {
		width: 160px;
		text-align: left;
		padding: 4px 8px 4px 0;
		font-weight: 600;
		vertical-align: top;
	}

	td {
		padding: 4px 0;
	}
`;

const HistoryTable = styled.table`
	width: 100%;
	border-collapse: collapse;
	table-layout: fixed;

	th,
	td {
		text-align: left;
		padding: 8px;
		border-bottom: 1px solid #f0f0f0;
		overflow: hidden;
	}

	th {
		font-weight: 600;
		background: #f9f9f9;
	}

	tr:last-child td {
		border-bottom: none;
	}

	code {
		font-size: 11px;
	}

	.col-timestamp {
		width: 170px;
		white-space: nowrap;
	}

	.col-products {
		width: 90px;
		text-align: center;
		white-space: nowrap;
	}

	.col-status {
		width: 200px;
		white-space: nowrap;
	}

	.col-import-id {
		width: auto;
	}

	.col-import-id code {
		display: flex;
		align-items: center;
		min-width: 0;
	}

	.col-import-id .id-start {
		flex: 0 1 auto;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.col-import-id .id-end {
		flex: 0 0 auto;
		white-space: nowrap;
	}
`;

const StatusPill = styled( Pill )`
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 2px 8px;
	border-radius: 2px;
	line-height: 16px;

	&.is-success {
		background: #edfaef;
		border-color: #edfaef;
		color: #005c12;
	}

	&.is-error {
		background: #fcf0f1;
		border-color: #fcf0f1;
		color: #8a2424;
	}

	&.is-warning {
		background: #fcf9e8;
		border-color: #fcf9e8;
		color: #674600;
	}

	&.is-info {
		background: #f0f6fc;
		border-color: #f0f6fc;
		color: #1d4a72;
	}

	&.is-neutral {
		background: #f0f0f0;
		border-color: #f0f0f0;
		color: #50575e;
	}

	svg {
		fill: currentColor;
	}
`;

const getStatusConfig = ( status ) => {
	switch ( status ) {
		case 'succeeded':
			return {
				label: __( 'Success', 'woocommerce-gateway-stripe' ),
				tone: 'is-success',
				icon: check,
			};
		case 'pending':
			return {
				label: __( 'Processing', 'woocommerce-gateway-stripe' ),
				tone: 'is-info',
				icon: pending,
			};
		case 'creating_records':
			return {
				label: __( 'Creating records', 'woocommerce-gateway-stripe' ),
				tone: 'is-info',
				icon: pending,
			};
		case 'queued':
			return {
				label: __( 'Queued', 'woocommerce-gateway-stripe' ),
				tone: 'is-info',
				icon: pending,
			};
		case 'validating':
			return {
				label: __( 'Validating', 'woocommerce-gateway-stripe' ),
				tone: 'is-info',
				icon: pending,
			};
		case 'failed':
			return {
				label: __( 'Failed', 'woocommerce-gateway-stripe' ),
				tone: 'is-error',
				icon: close,
			};
		case 'succeeded_with_errors':
			return {
				label: __( 'Partial success', 'woocommerce-gateway-stripe' ),
				tone: 'is-warning',
				icon: warning,
			};
		default:
			return {
				label: __( 'Unknown', 'woocommerce-gateway-stripe' ),
				tone: 'is-neutral',
				icon: help,
			};
	}
};

const SyncStatusBadge = ( { status } ) => {
	const { label, tone, icon } = getStatusConfig( status );
	return (
		<StatusPill className={ tone }>
			<Icon icon={ icon } size={ 14 } />
			<span>{ label }</span>
		</StatusPill>
	);
};

const formatTimestamp = ( timestamp ) => {
	if ( ! timestamp ) return '—';
	return new Date( timestamp * 1000 ).toLocaleString();
};

const humanTimeDiff = ( timestamp ) => {
	if ( ! timestamp ) return '';
	const diffSec = Math.floor( Date.now() / 1000 ) - timestamp;
	if ( diffSec < 60 ) return __( 'just now', 'woocommerce-gateway-stripe' );
	if ( diffSec < 3600 ) {
		const m = Math.floor( diffSec / 60 );
		if ( m === 1 )
			return __( '1 minute ago', 'woocommerce-gateway-stripe' );
		return sprintf(
			/* translators: %d: number of minutes */
			__( '%d minutes ago', 'woocommerce-gateway-stripe' ),
			m
		);
	}
	const h = Math.floor( diffSec / 3600 );
	if ( h === 1 ) return __( '1 hour ago', 'woocommerce-gateway-stripe' );
	return sprintf(
		/* translators: %d: number of hours */
		__( '%d hours ago', 'woocommerce-gateway-stripe' ),
		h
	);
};

const AgenticCommerceSyncStatus = () => {
	const [ data, setData ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSyncing, setIsSyncing ] = useState( false );
	const [ hasError, setHasError ] = useState( false );

	const fetchStatus = useCallback( async () => {
		setIsLoading( true );
		setHasError( false );
		try {
			const result = await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce/status',
			} );
			setData( result );
		} catch ( err ) {
			setHasError( true );
			dispatch( 'core/notices' ).createErrorNotice(
				__(
					'Failed to load sync status.',
					'woocommerce-gateway-stripe'
				)
			);
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchStatus();
	}, [ fetchStatus ] );

	const handleSync = async () => {
		setIsSyncing( true );
		try {
			await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce/sync',
				method: 'POST',
			} );
			dispatch( 'core/notices' ).createSuccessNotice(
				__(
					'Sync triggered successfully.',
					'woocommerce-gateway-stripe'
				)
			);
			await fetchStatus();
		} catch ( err ) {
			dispatch( 'core/notices' ).createErrorNotice(
				__(
					'Sync failed. Check the WooCommerce logs for details.',
					'woocommerce-gateway-stripe'
				)
			);
		} finally {
			setIsSyncing( false );
		}
	};

	const { last_sync: lastSync, history, next_sync: nextSync } = data ?? {};

	const nextSyncLabel = () => {
		if ( ! nextSync ) return null;
		const secondsUntil = nextSync - Math.floor( Date.now() / 1000 );
		if ( secondsUntil <= 0 )
			return __(
				'Next automatic sync: imminent.',
				'woocommerce-gateway-stripe'
			);
		const minutes = Math.ceil( secondsUntil / 60 );
		if ( minutes === 1 ) {
			return __(
				'Next automatic sync: in 1 minute.',
				'woocommerce-gateway-stripe'
			);
		}
		return sprintf(
			/* translators: %d: number of minutes until next sync */
			__(
				'Next automatic sync: in %d minutes.',
				'woocommerce-gateway-stripe'
			),
			minutes
		);
	};

	const visibleHistory = history?.slice( 0, HISTORY_ROW_LIMIT ) ?? [];
	const hiddenHistoryCount = Math.max(
		0,
		( history?.length ?? 0 ) - visibleHistory.length
	);

	return (
		<>
			<p className="description" style={ { marginTop: '16px' } }>
				{ __(
					'Monitors the product feed sync status for the agentic commerce integration.',
					'woocommerce-gateway-stripe'
				) }{ ' ' }
				<ExternalLink href="https://dashboard.stripe.com/data-management/import-sets">
					{ __(
						'View import results on the Stripe Dashboard',
						'woocommerce-gateway-stripe'
					) }
				</ExternalLink>
			</p>

			<Card style={ { marginBottom: '20px' } }>
				<CardHeader>
					<h2 style={ { margin: 0, fontSize: '14px' } }>
						{ __(
							'Product feed status',
							'woocommerce-gateway-stripe'
						) }
					</h2>
				</CardHeader>
				<CardBody>
					{ isLoading && (
						<p>
							{ __( 'Loading…', 'woocommerce-gateway-stripe' ) }
						</p>
					) }
					{ ! isLoading && ! hasError && ! lastSync && (
						<p>
							{ __(
								'No syncs yet. Feed will sync automatically every 15 minutes.',
								'woocommerce-gateway-stripe'
							) }
						</p>
					) }
					{ ! isLoading && lastSync && (
						<>
							<SyncStatusBadge status={ lastSync.status } />

							<DetailsTable>
								<tbody>
									{ lastSync.timestamp && (
										<tr>
											<th>
												{ __(
													'Last sync',
													'woocommerce-gateway-stripe'
												) }
											</th>
											<td>
												{ humanTimeDiff(
													lastSync.timestamp
												) }{ ' ' }
												<small>
													(
													{ formatTimestamp(
														lastSync.timestamp
													) }
													)
												</small>
											</td>
										</tr>
									) }
									{ lastSync.products !== null && (
										<tr>
											<th>
												{ __(
													'Products synced',
													'woocommerce-gateway-stripe'
												) }
											</th>
											<td>
												{ lastSync.products.toLocaleString() }
											</td>
										</tr>
									) }
									{ lastSync.import_set_id && (
										<tr>
											<th>
												{ __(
													'ImportSet ID',
													'woocommerce-gateway-stripe'
												) }
											</th>
											<td>
												<code>
													{ lastSync.import_set_id }
												</code>
											</td>
										</tr>
									) }
									{ lastSync.file_id && (
										<tr>
											<th>
												{ __(
													'File ID',
													'woocommerce-gateway-stripe'
												) }
											</th>
											<td>
												<code>
													{ lastSync.file_id }
												</code>
											</td>
										</tr>
									) }
								</tbody>
							</DetailsTable>

							{ nextSyncLabel() && (
								<p className="description">
									{ nextSyncLabel() }
								</p>
							) }

							{ lastSync.error && (
								<Notice status="error" isDismissible={ false }>
									<strong>
										{ __(
											'Last sync error:',
											'woocommerce-gateway-stripe'
										) }
									</strong>{ ' ' }
									{ lastSync.error }
								</Notice>
							) }
						</>
					) }

					<Flex
						justify="flex-start"
						gap={ 2 }
						style={ { marginTop: '16px' } }
					>
						<Button
							variant="primary"
							isBusy={ isSyncing }
							disabled={ isSyncing || isLoading }
							onClick={ handleSync }
						>
							{ isSyncing
								? __( 'Syncing…', 'woocommerce-gateway-stripe' )
								: __(
										'Sync now',
										'woocommerce-gateway-stripe'
								  ) }
						</Button>
						<Button
							variant="secondary"
							href="/wp-admin/admin.php?page=wc-status&tab=logs"
						>
							{ __( 'View logs', 'woocommerce-gateway-stripe' ) }
						</Button>
					</Flex>
				</CardBody>
			</Card>

			<Card style={ { marginBottom: '20px' } }>
				<CardHeader>
					<h2 style={ { margin: 0, fontSize: '14px' } }>
						{ __( 'Recent syncs', 'woocommerce-gateway-stripe' ) }
					</h2>
				</CardHeader>
				<CardBody>
					{ isLoading && (
						<p>
							{ __( 'Loading…', 'woocommerce-gateway-stripe' ) }
						</p>
					) }
					{ ! isLoading && ! hasError && ! history?.length && (
						<p>
							{ __(
								'No sync history available.',
								'woocommerce-gateway-stripe'
							) }
						</p>
					) }
					{ ! isLoading && !! visibleHistory.length && (
						<>
							<HistoryTable>
								<thead>
									<tr>
										<th className="col-timestamp">
											{ __(
												'Timestamp',
												'woocommerce-gateway-stripe'
											) }
										</th>
										<th className="col-products">
											{ __(
												'Products',
												'woocommerce-gateway-stripe'
											) }
										</th>
										<th className="col-status">
											{ __(
												'Status',
												'woocommerce-gateway-stripe'
											) }
										</th>
										<th className="col-import-id">
											{ __(
												'Import ID',
												'woocommerce-gateway-stripe'
											) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ visibleHistory.map( ( entry, i ) => (
										<tr key={ i }>
											<td className="col-timestamp">
												{ entry.timestamp
													? new Date(
															entry.timestamp *
																1000
													  ).toLocaleString( [], {
															year: 'numeric',
															month: '2-digit',
															day: '2-digit',
															hour: '2-digit',
															minute: '2-digit',
													  } )
													: '—' }
											</td>
											<td className="col-products">
												{ entry.products !== null
													? entry.products.toLocaleString()
													: '—' }
											</td>
											<td className="col-status">
												<SyncStatusBadge
													status={ entry.status }
												/>
												{ entry.error && (
													<span title={ entry.error }>
														{ ' ' }
														ℹ
													</span>
												) }
											</td>
											<td className="col-import-id">
												{ entry.import_set_id ? (
													<code
														title={
															entry.import_set_id
														}
													>
														<span className="id-start">
															{ entry.import_set_id.slice(
																0,
																-6
															) }
														</span>
														<span className="id-end">
															{ entry.import_set_id.slice(
																-6
															) }
														</span>
													</code>
												) : (
													'—'
												) }
											</td>
										</tr>
									) ) }
								</tbody>
							</HistoryTable>
							{ hiddenHistoryCount > 0 && (
								<p
									className="description"
									style={ { marginTop: '12px' } }
								>
									{ sprintf(
										/* translators: %d: number of older sync entries not shown. */
										__(
											'Showing the %1$d most recent syncs (%2$d older entries hidden).',
											'woocommerce-gateway-stripe'
										),
										visibleHistory.length,
										hiddenHistoryCount
									) }{ ' ' }
									<ExternalLink href="https://dashboard.stripe.com/data-management/import-sets">
										{ __(
											'View all on the Stripe Dashboard',
											'woocommerce-gateway-stripe'
										) }
									</ExternalLink>
								</p>
							) }
						</>
					) }
				</CardBody>
			</Card>
		</>
	);
};

export default AgenticCommerceSyncStatus;
