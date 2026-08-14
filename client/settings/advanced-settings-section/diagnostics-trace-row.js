import React from 'react';
import classNames from 'classnames';
import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

const STATUS_LABELS = {
	pending: 'Pending',
	failed: 'Failed',
	completed: 'Succeeded',
	abandoned: 'Abandoned',
};

const formatRelative = ( unixSeconds, nowSeconds ) => {
	if ( ! unixSeconds ) {
		return '—';
	}
	const diff = Math.max( 0, nowSeconds - unixSeconds );
	if ( diff < 60 ) {
		return __( 'just now', 'woocommerce-gateway-stripe' );
	}
	if ( diff < 60 * 60 ) {
		const mins = Math.round( diff / 60 );
		return sprintf(
			/* translators: %d: minutes ago */
			_n(
				'%d min ago',
				'%d mins ago',
				mins,
				'woocommerce-gateway-stripe'
			),
			mins
		);
	}
	if ( diff < 60 * 60 * 24 ) {
		const hrs = Math.round( diff / 3600 );
		return sprintf(
			/* translators: %d: hours ago */
			_n( '%d hr ago', '%d hrs ago', hrs, 'woocommerce-gateway-stripe' ),
			hrs
		);
	}
	const days = Math.round( diff / 86400 );
	return sprintf(
		/* translators: %d: days ago */
		_n( '%d day ago', '%d days ago', days, 'woocommerce-gateway-stripe' ),
		days
	);
};

const formatDuration = ( startUnix, endUnix ) => {
	if ( ! startUnix || ! endUnix || endUnix <= startUnix ) {
		return '—';
	}
	const seconds = endUnix - startUnix;
	if ( seconds < 60 ) {
		return sprintf(
			/* translators: %d: seconds */
			__( '%ds', 'woocommerce-gateway-stripe' ),
			seconds
		);
	}
	const mins = ( seconds / 60 ).toFixed( 1 );
	return sprintf(
		/* translators: %s: minutes */
		__( '%sm', 'woocommerce-gateway-stripe' ),
		mins
	);
};

// Derive a one-line summary from whatever signals the trace happens to
// have. Pending traces with no events fall through to a generic label
// rather than rendering an empty cell.
const getSummary = ( trace ) => {
	const meta = trace.meta || {};
	const events = Array.isArray( trace.events ) ? trace.events : [];
	const last = events[ events.length - 1 ];
	const reason =
		last && ( last.reason || last.message_truncated || last.kind );
	const orderId = meta.order_id;

	if ( reason && orderId ) {
		return sprintf(
			/* translators: 1: failure reason or event kind, 2: order id */
			__( '%1$s · order #%2$s', 'woocommerce-gateway-stripe' ),
			reason,
			orderId
		);
	}
	if ( orderId ) {
		return sprintf(
			/* translators: %s: order id */
			__( 'order #%s', 'woocommerce-gateway-stripe' ),
			orderId
		);
	}
	if ( reason ) {
		return reason;
	}
	return __(
		'Session pending — no events yet',
		'woocommerce-gateway-stripe'
	);
};

const DiagnosticsTraceRow = ( { trace, nowSeconds, onCopy, onView } ) => {
	const status = trace.status || 'pending';
	const statusLabel = STATUS_LABELS[ status ] || status;
	return (
		<div
			className="wc-stripe-diagnostics-trace-row"
			data-testid={ `trace-row-${ trace.id }` }
		>
			<span
				className={ classNames(
					'wc-stripe-diagnostics-status',
					`wc-stripe-diagnostics-status--${ status }`
				) }
			>
				<span
					className={ classNames(
						'wc-stripe-diagnostics-status-dot',
						`wc-stripe-diagnostics-status-dot--${ status }`
					) }
					aria-hidden="true"
				/>
				{ statusLabel }
			</span>
			<div className="wc-stripe-diagnostics-trace-row__main">
				<div className="wc-stripe-diagnostics-trace-row__heading">
					<span className="wc-stripe-diagnostics-trace-row__id">
						{ trace.id }
					</span>
					<span className="wc-stripe-diagnostics-trace-row__when">
						{ '· ' }
						{ formatRelative(
							trace.created_at || trace.updated_at,
							nowSeconds
						) }
					</span>
				</div>
				<div className="wc-stripe-diagnostics-trace-row__summary">
					{ getSummary( trace ) }
				</div>
			</div>
			<span
				className="wc-stripe-diagnostics-trace-row__duration"
				title={ __(
					'Session duration (first to last event)',
					'woocommerce-gateway-stripe'
				) }
			>
				{ formatDuration( trace.created_at, trace.updated_at ) }
			</span>
			<div className="wc-stripe-diagnostics-trace-row__actions">
				<Button
					variant="tertiary"
					onClick={ () => onCopy( trace ) }
					aria-label={ sprintf(
						/* translators: %s: trace id */
						__( 'Copy trace %s', 'woocommerce-gateway-stripe' ),
						trace.id
					) }
				>
					{ __( 'Copy', 'woocommerce-gateway-stripe' ) }
				</Button>
				<Button
					variant="tertiary"
					onClick={ () => onView( trace ) }
					aria-label={ sprintf(
						/* translators: %s: trace id */
						__( 'View trace %s', 'woocommerce-gateway-stripe' ),
						trace.id
					) }
				>
					{ __( 'View', 'woocommerce-gateway-stripe' ) }
				</Button>
			</div>
		</div>
	);
};

export default DiagnosticsTraceRow;
export { getSummary, formatDuration, formatRelative };
