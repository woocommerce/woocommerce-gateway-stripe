import React, { useEffect, useState, useCallback, useMemo } from 'react';
import DiagnosticsTraceToolbar, {
	FILTER_ALL,
	FILTER_FAILED,
} from './diagnostics-trace-toolbar';
import DiagnosticsTraceRow from './diagnostics-trace-row';
import DiagnosticsTraceViewModal from './diagnostics-trace-view-modal';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { NAMESPACE } from 'wcstripe/data/constants';
import { recordEvent } from 'wcstripe/tracking';

// Stable id so repeated clicks replace the previous notice rather than stack.
const NOTICE_ID = 'wc-stripe/diagnostics-copy-traces';

// Clipboard writes silently truncate or fail above a few MB on most
// browsers, and ticket forms strip large pastes long before that. Falling
// back to a file download above this size is cheap insurance.
const CLIPBOARD_BYTE_THRESHOLD = 1024 * 1024;

const TRACES_PATH = `${ NAMESPACE }/diagnostics/traces`;

const downloadAsFile = ( contents ) => {
	const blob = new Blob( [ contents ], { type: 'application/json' } );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	const stamp = new Date().toISOString().replace( /[:.]/g, '-' );
	link.href = url;
	link.download = `wc-stripe-diagnostics-${ stamp }.json`;
	document.body.appendChild( link );
	link.click();
	document.body.removeChild( link );
	// Defer the revoke to give Safari/iOS time to start the download —
	// synchronous revoke can produce a 0-byte file there.
	setTimeout( () => URL.revokeObjectURL( url ), 0 );
};

const copyOrDownload = async ( payload ) => {
	const json = JSON.stringify( payload, null, 2 );
	const bytes = new Blob( [ json ] ).size;
	if ( bytes > CLIPBOARD_BYTE_THRESHOLD ) {
		downloadAsFile( json );
		return 'download';
	}
	await navigator.clipboard.writeText( json );
	return 'clipboard';
};

const DiagnosticsTraces = ( {
	isRecording = false,
	captureLimit = 10,
	captureLimitPresets,
	onChangeCaptureLimit,
} ) => {
	const [ traces, setTraces ] = useState( null );
	const [ filter, setFilter ] = useState( FILTER_ALL );
	const [ viewing, setViewing ] = useState( null );
	const [ isCopying, setIsCopying ] = useState( false );
	const [ isClearing, setIsClearing ] = useState( false );
	const { createSuccessNotice, createErrorNotice, removeNotice } =
		useDispatch( 'core/notices' );

	const refreshTraces = useCallback( () => {
		apiFetch( { path: TRACES_PATH } )
			.then( ( response ) => setTraces( response.traces || [] ) )
			.catch( () => setTraces( [] ) );
	}, [] );

	useEffect( () => {
		refreshTraces();
	}, [ refreshTraces ] );

	const sorted = useMemo( () => {
		if ( ! Array.isArray( traces ) ) {
			return [];
		}
		// Newest first matches what the merchant just produced — they
		// usually want the trace they just triggered at the top.
		return [ ...traces ].sort(
			( a, b ) => ( b.created_at || 0 ) - ( a.created_at || 0 )
		);
	}, [ traces ] );

	const failedCount = useMemo(
		() => sorted.filter( ( t ) => t.status === 'failed' ).length,
		[ sorted ]
	);

	const visible = useMemo(
		() =>
			filter === FILTER_FAILED
				? sorted.filter( ( t ) => t.status === 'failed' )
				: sorted,
		[ sorted, filter ]
	);

	// Until the first fetch returns, render nothing — keeps the card
	// quiet during the half-second of admin page boot.
	if ( traces === null ) {
		return null;
	}

	const totalCount = sorted.length;

	if ( totalCount === 0 ) {
		return (
			<div className="wc-stripe-diagnostics-traces wc-stripe-diagnostics-traces--empty">
				<p>
					{ isRecording
						? __(
								'No traces stored yet. Traces will appear here as customers reach checkout.',
								'woocommerce-gateway-stripe'
						  )
						: __(
								'No traces stored. Turn on diagnostics to start capturing.',
								'woocommerce-gateway-stripe'
						  ) }
				</p>
			</div>
		);
	}

	const reportError = ( message ) => {
		createErrorNotice( message, { id: NOTICE_ID } );
	};

	const reportSuccess = ( message ) => {
		createSuccessNotice( message, { id: NOTICE_ID } );
	};

	const handleCopy = async ( payload, scope ) => {
		setIsCopying( true );
		removeNotice( NOTICE_ID );
		try {
			const result = await copyOrDownload( payload );
			const count = Array.isArray( payload ) ? payload.length : 1;
			recordEvent( 'wcstripe_diagnostics_copy_traces', {
				scope,
				result,
				count,
			} );
			if ( result === 'download' ) {
				reportSuccess(
					__(
						'Trace bundle was too large to copy — downloaded as a file instead.',
						'woocommerce-gateway-stripe'
					)
				);
			} else {
				reportSuccess(
					sprintf(
						/* translators: %d: number of traces copied */
						_n(
							'Copied %d trace to clipboard.',
							'Copied %d traces to clipboard.',
							count,
							'woocommerce-gateway-stripe'
						),
						count
					)
				);
			}
		} catch ( err ) {
			recordEvent( 'wcstripe_diagnostics_copy_traces', {
				scope,
				result: 'error',
				count: null,
			} );
			reportError(
				__(
					'Could not copy traces. Try again.',
					'woocommerce-gateway-stripe'
				)
			);
		} finally {
			setIsCopying( false );
		}
	};

	const handleBulkCopy = () => {
		const payload =
			filter === FILTER_FAILED
				? sorted.filter( ( t ) => t.status === 'failed' )
				: sorted;
		handleCopy( payload, filter === FILTER_FAILED ? 'failed' : 'all' );
	};

	const handleRowCopy = ( trace ) => {
		handleCopy( trace, 'single' );
	};

	const handleRowView = ( trace ) => {
		recordEvent( 'wcstripe_diagnostics_view_trace', { id: trace.id } );
		setViewing( trace );
	};

	const handleClear = async () => {
		setIsClearing( true );
		removeNotice( NOTICE_ID );
		try {
			const response = await apiFetch( {
				path: TRACES_PATH,
				method: 'DELETE',
			} );
			recordEvent( 'wcstripe_diagnostics_clear_traces', {
				deleted: response?.deleted ?? null,
			} );
			reportSuccess(
				__( 'Cleared all stored traces.', 'woocommerce-gateway-stripe' )
			);
			refreshTraces();
		} catch ( err ) {
			recordEvent( 'wcstripe_diagnostics_clear_traces', {
				deleted: null,
				result: 'error',
			} );
			reportError(
				__(
					'Could not clear traces. Try again.',
					'woocommerce-gateway-stripe'
				)
			);
		} finally {
			setIsClearing( false );
		}
	};

	const nowSeconds = Math.floor( Date.now() / 1000 );

	return (
		<div className="wc-stripe-diagnostics-traces">
			<DiagnosticsTraceToolbar
				totalCount={ totalCount }
				failedCount={ failedCount }
				filter={ filter }
				onFilterChange={ setFilter }
				isRecording={ isRecording }
				captureLimit={ captureLimit }
				captureLimitPresets={ captureLimitPresets }
				onChangeCaptureLimit={ onChangeCaptureLimit }
				onCopy={ handleBulkCopy }
				onClear={ handleClear }
				isCopying={ isCopying }
				isClearing={ isClearing }
			/>
			<div className="wc-stripe-diagnostics-trace-list" role="list">
				{ visible.length === 0 ? (
					<div className="wc-stripe-diagnostics-trace-list__empty">
						{ __(
							'No traces match this filter.',
							'woocommerce-gateway-stripe'
						) }
					</div>
				) : (
					visible.map( ( trace ) => (
						<DiagnosticsTraceRow
							key={ trace.id }
							trace={ trace }
							nowSeconds={ nowSeconds }
							onCopy={ handleRowCopy }
							onView={ handleRowView }
						/>
					) )
				) }
			</div>
			<DiagnosticsTraceViewModal
				trace={ viewing }
				onClose={ () => setViewing( null ) }
				onCopy={ ( trace ) => handleCopy( trace, 'single' ) }
			/>
		</div>
	);
};

export default DiagnosticsTraces;
