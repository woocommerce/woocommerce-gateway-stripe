/* global wc_stripe_settings_params */
import React, { useState, useEffect, useCallback } from 'react';
import styled from '@emotion/styled';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
} from '@wordpress/components';

// Threshold beyond which a missed scheduled sync is surfaced as a warning.
// Chosen comfortably larger than the sync interval so a single slow run
// does not trigger a false positive.
const OVERDUE_WARNING_THRESHOLD_SECONDS = 10 * 60;

const CardTitle = styled.h2`
	margin: 0;
	font-size: 14px;
	font-weight: 600;
`;

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

const Actions = styled.div`
	display: flex;
	gap: 8px;
	margin-top: 16px;
	align-items: center;
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
	creating_records: {
		label: __( 'Creating records', 'woocommerce-gateway-stripe' ),
		className: 'info',
		icon: '⏳',
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
	if ( ! timestamp ) {
		return '—';
	}
	return new Date( timestamp * 1000 ).toLocaleString();
};

const humanTimeDiff = ( timestamp ) => {
	if ( ! timestamp ) {
		return '';
	}
	const diffSec = Math.floor( Date.now() / 1000 ) - timestamp;
	if ( diffSec < 60 ) {
		return __( 'just now', 'woocommerce-gateway-stripe' );
	}
	if ( diffSec < 3600 ) {
		const m = Math.floor( diffSec / 60 );
		return sprintf(
			/* translators: %d: number of minutes */
			_n(
				'%d minute ago',
				'%d minutes ago',
				m,
				'woocommerce-gateway-stripe'
			),
			m
		);
	}
	const h = Math.floor( diffSec / 3600 );
	return sprintf(
		/* translators: %d: number of hours */
		_n( '%d hour ago', '%d hours ago', h, 'woocommerce-gateway-stripe' ),
		h
	);
};

const AgenticCommercePanel = () => {
	const [ data, setData ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSyncing, setIsSyncing ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loadError, setLoadError ] = useState( false );

	const fetchStatus = useCallback( async () => {
		setIsLoading( true );
		try {
			const result = await apiFetch( {
				path: '/wc/v3/wc_stripe/agentic-commerce/status',
			} );
			setData( result );
			setLoadError( false );
		} catch ( err ) {
			setLoadError( true );
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

	const secondsUntilNextSync =
		typeof nextSync === 'number'
			? nextSync - Math.floor( Date.now() / 1000 )
			: null;

	const isNextSyncOverdue =
		secondsUntilNextSync !== null &&
		secondsUntilNextSync < -OVERDUE_WARNING_THRESHOLD_SECONDS;

	const getNextSyncLabel = () => {
		if ( secondsUntilNextSync === null ) {
			return null;
		}
		if ( secondsUntilNextSync <= 0 ) {
			return __(
				'Next automatic sync: imminent.',
				'woocommerce-gateway-stripe'
			);
		}
		const minutes = Math.ceil( secondsUntilNextSync / 60 );
		return sprintf(
			/* translators: %d: number of minutes until next sync */
			_n(
				'Next automatic sync: in %d minute.',
				'Next automatic sync: in %d minutes.',
				minutes,
				'woocommerce-gateway-stripe'
			),
			minutes
		);
	};

	const importSetsUrl =
		wc_stripe_settings_params?.agentic_commerce_import_sets_url ?? // eslint-disable-line camelcase
		'https://dashboard.stripe.com/data-management/import-sets';
	const logsUrl =
		wc_stripe_settings_params?.agentic_commerce_logs_url ?? // eslint-disable-line camelcase
		'/wp-admin/admin.php?page=wc-status&tab=logs';
	const nextSyncText = getNextSyncLabel();

	const overdueMinutes =
		secondsUntilNextSync !== null
			? Math.floor( Math.abs( secondsUntilNextSync ) / 60 )
			: 0;

	return (
		<div>
			<p className="description">
				{ __(
					'Monitor the product feed sync status for the Agentic Commerce integration.',
					'woocommerce-gateway-stripe'
				) }{ ' ' }
				<a
					href={ importSetsUrl }
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

			{ isNextSyncOverdue && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: number of minutes the scheduled sync is overdue. */
						_n(
							'The scheduled sync is overdue by %d minute. Check that Action Scheduler is running on this site.',
							'The scheduled sync is overdue by %d minutes. Check that Action Scheduler is running on this site.',
							overdueMinutes,
							'woocommerce-gateway-stripe'
						),
						overdueMinutes
					) }
				</Notice>
			) }

			<Card>
				<CardHeader>
					<CardTitle>
						{ __(
							'Product Feed Status',
							'woocommerce-gateway-stripe'
						) }
					</CardTitle>
				</CardHeader>
				<CardBody>
					{ isLoading && (
						<p>
							{ __( 'Loading…', 'woocommerce-gateway-stripe' ) }
						</p>
					) }
					{ ! isLoading && ! lastSync && ! loadError && (
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
												{ Number(
													lastSync.products
												).toLocaleString() }
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

							{ nextSyncText && (
								<p className="description">{ nextSyncText }</p>
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
								: __(
										'Sync Now',
										'woocommerce-gateway-stripe'
								  ) }
						</Button>
						<Button variant="secondary" href={ logsUrl }>
							{ __( 'View Logs', 'woocommerce-gateway-stripe' ) }
						</Button>
					</Actions>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					<CardTitle>
						{ __( 'Recent Syncs', 'woocommerce-gateway-stripe' ) }
					</CardTitle>
				</CardHeader>
				<CardBody>
					{ isLoading && (
						<p>
							{ __( 'Loading…', 'woocommerce-gateway-stripe' ) }
						</p>
					) }
					{ ! isLoading && ! history?.length && ! loadError && (
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
												? Number(
														entry.products
												  ).toLocaleString()
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
												<code>
													{ entry.import_set_id }
												</code>
											) : (
												'—'
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</HistoryTable>
					) }
				</CardBody>
			</Card>
		</div>
	);
};

export default AgenticCommercePanel;
