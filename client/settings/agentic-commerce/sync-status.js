import React, { useState, useEffect, useCallback } from 'react';
import styled from '@emotion/styled';
import { Card, CardTitle, Actions } from './styled';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { Button, Notice } from '@wordpress/components';

const StatusBadge = styled.span`
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
	margin-bottom: 12px;

	&.success {
		background: #d4edda;
		color: #155724;
	}
	&.error {
		background: #f8d7da;
		color: #721c24;
	}
	&.warning {
		background: #fff3cd;
		color: #856404;
	}
	&.info {
		background: #d1ecf1;
		color: #0c5460;
	}
	&.unknown {
		background: #e2e3e5;
		color: #383d41;
	}
`;

const DetailsTable = styled.table`
	border-collapse: collapse;
	margin-bottom: 12px;
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

	th,
	td {
		text-align: left;
		padding: 8px;
		border-bottom: 1px solid #f0f0f0;
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
`;

const STATUS_CONFIG = {
	succeeded: {
		label: __( 'Success', 'woocommerce-gateway-stripe' ),
		className: 'success',
		icon: '✓',
	},
	pending: {
		label: __( 'Processing', 'woocommerce-gateway-stripe' ),
		className: 'info',
		icon: '⏳',
	},
	failed: {
		label: __( 'Failed', 'woocommerce-gateway-stripe' ),
		className: 'error',
		icon: '✗',
	},
	succeeded_with_errors: {
		label: __( 'Partial Success', 'woocommerce-gateway-stripe' ),
		className: 'warning',
		icon: '⚠',
	},
};

const SyncStatusBadge = ( { status } ) => {
	const config = STATUS_CONFIG[ status ] ?? {
		label: __( 'Unknown', 'woocommerce-gateway-stripe' ),
		className: 'unknown',
		icon: '?',
	};
	return (
		<StatusBadge className={ config.className }>
			{ config.icon } { config.label }
		</StatusBadge>
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

// Minimal sprintf for %d substitution.
const sprintf = ( fmt, ...args ) => fmt.replace( /%d/g, () => args.shift() );

const AgenticCommerceSyncStatus = () => {
	const [ data, setData ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSyncing, setIsSyncing ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const fetchStatus = useCallback( async () => {
		setIsLoading( true );
		try {
			const result = await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce',
			} );
			setData( result );
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ??
					__(
						'Failed to load sync status.',
						'woocommerce-gateway-stripe'
					),
			} );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchStatus();
	}, [ fetchStatus ] );

	const handleSync = async () => {
		setIsSyncing( true );
		setNotice( null );
		try {
			await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce/sync',
				method: 'POST',
			} );
			setNotice( {
				status: 'success',
				message: __(
					'Sync triggered successfully.',
					'woocommerce-gateway-stripe'
				),
			} );
			await fetchStatus();
		} catch ( err ) {
			setNotice( {
				status: 'error',
				message:
					err?.message ??
					__(
						'Sync failed. Check the WooCommerce logs for details.',
						'woocommerce-gateway-stripe'
					),
			} );
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

	return (
		<>
			<p className="description">
				{ __(
					'Monitors the product feed sync status for the agentic commerce integration.',
					'woocommerce-gateway-stripe'
				) }{ ' ' }
				<a
					href="https://dashboard.stripe.com/data-management/import-sets"
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __(
						'View import results on the Stripe Dashboard',
						'woocommerce-gateway-stripe'
					) }
				</a>
			</p>

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
					isDismissible
				>
					{ notice.message }
				</Notice>
			) }

			<Card>
				<CardTitle>
					{ __(
						'Product Feed Status',
						'woocommerce-gateway-stripe'
					) }
				</CardTitle>

				{ isLoading && (
					<p>{ __( 'Loading…', 'woocommerce-gateway-stripe' ) }</p>
				) }
				{ ! isLoading && ! lastSync && (
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
												'Last Sync',
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
												'Products Synced',
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
											<code>{ lastSync.file_id }</code>
										</td>
									</tr>
								) }
							</tbody>
						</DetailsTable>

						{ nextSyncLabel() && (
							<p className="description">{ nextSyncLabel() }</p>
						) }

						{ lastSync.error && (
							<Notice status="error" isDismissible={ false }>
								<strong>
									{ __(
										'Last Sync Error:',
										'woocommerce-gateway-stripe'
									) }
								</strong>{ ' ' }
								{ lastSync.error }
							</Notice>
						) }
					</>
				) }

				<Actions>
					<Button
						variant="primary"
						isBusy={ isSyncing }
						disabled={ isSyncing || isLoading }
						onClick={ handleSync }
					>
						{ isSyncing
							? __( 'Syncing…', 'woocommerce-gateway-stripe' )
							: __( 'Sync Now', 'woocommerce-gateway-stripe' ) }
					</Button>
					<Button
						variant="secondary"
						href="/wp-admin/admin.php?page=wc-status&tab=logs"
					>
						{ __( 'View Logs', 'woocommerce-gateway-stripe' ) }
					</Button>
				</Actions>
			</Card>

			<Card>
				<CardTitle>
					{ __( 'Recent Syncs', 'woocommerce-gateway-stripe' ) }
				</CardTitle>

				{ isLoading && (
					<p>{ __( 'Loading…', 'woocommerce-gateway-stripe' ) }</p>
				) }
				{ ! isLoading && ! history?.length && (
					<p>
						{ __(
							'No sync history available.',
							'woocommerce-gateway-stripe'
						) }
					</p>
				) }
				{ ! isLoading && !! history?.length && (
					<HistoryTable>
						<thead>
							<tr>
								<th>
									{ __(
										'Timestamp',
										'woocommerce-gateway-stripe'
									) }
								</th>
								<th>
									{ __(
										'Products',
										'woocommerce-gateway-stripe'
									) }
								</th>
								<th>
									{ __(
										'Status',
										'woocommerce-gateway-stripe'
									) }
								</th>
								<th>
									{ __(
										'Import ID',
										'woocommerce-gateway-stripe'
									) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ history.map( ( entry, i ) => (
								<tr key={ i }>
									<td>
										{ entry.timestamp
											? new Date(
													entry.timestamp * 1000
											  ).toLocaleString( [], {
													year: 'numeric',
													month: '2-digit',
													day: '2-digit',
													hour: '2-digit',
													minute: '2-digit',
											  } )
											: '—' }
									</td>
									<td>
										{ entry.products !== null
											? entry.products.toLocaleString()
											: '—' }
									</td>
									<td>
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
									<td>
										{ entry.import_set_id ? (
											<code>{ entry.import_set_id }</code>
										) : (
											'—'
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</HistoryTable>
				) }
			</Card>
		</>
	);
};

export default AgenticCommerceSyncStatus;
