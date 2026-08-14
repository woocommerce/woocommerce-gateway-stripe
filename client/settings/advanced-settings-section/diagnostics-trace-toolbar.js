import React, { useState } from 'react';
import classNames from 'classnames';
import { Button } from '@wordpress/components';
import { createInterpolateElement } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';

const FILTER_ALL = 'all';
const FILTER_FAILED = 'failed';

const DEFAULT_LIMIT = 10;

const DiagnosticsTraceToolbar = ( {
	totalCount,
	failedCount,
	filter,
	onFilterChange,
	isRecording,
	captureLimit = DEFAULT_LIMIT,
	captureLimitPresets = [],
	onChangeCaptureLimit,
	onCopy,
	onClear,
	isCopying,
	isClearing,
} ) => {
	const [ isConfirmingClear, setIsConfirmingClear ] = useState( false );

	const handleClearConfirm = () => {
		setIsConfirmingClear( false );
		onClear();
	};

	const copyLabel =
		filter === FILTER_FAILED
			? __( 'Copy failed', 'woocommerce-gateway-stripe' )
			: __( 'Copy all', 'woocommerce-gateway-stripe' );

	// Pinned at 100% so a reduced limit doesn't overflow the bar.
	const progressPct = Math.min( 100, ( totalCount / captureLimit ) * 100 );

	return (
		<div className="wc-stripe-diagnostics-toolbar">
			<div className="wc-stripe-diagnostics-toolbar__row wc-stripe-diagnostics-toolbar__row--status">
				<div className="wc-stripe-diagnostics-toolbar__status">
					<span
						className={ classNames(
							'wc-stripe-diagnostics-recording-indicator',
							{
								'is-recording': isRecording,
							}
						) }
						aria-hidden="true"
					/>
					<span className="wc-stripe-diagnostics-toolbar__status-label">
						{ isRecording
							? __( 'Recording', 'woocommerce-gateway-stripe' )
							: __(
									'Not recording',
									'woocommerce-gateway-stripe'
							  ) }
					</span>
					<span
						className="wc-stripe-diagnostics-toolbar__separator"
						aria-hidden="true"
					>
						·
					</span>
					<span className="wc-stripe-diagnostics-toolbar__count">
						{ sprintf(
							/* translators: 1: captured trace count, 2: capture limit. */
							__(
								'%1$d of %2$d captured',
								'woocommerce-gateway-stripe'
							),
							totalCount,
							captureLimit
						) }
						{ failedCount > 0 && (
							<>
								{ ' · ' }
								<span className="wc-stripe-diagnostics-toolbar__failed-count">
									{ sprintf(
										/* translators: %d: number of failed traces */
										_n(
											'%d failed',
											'%d failed',
											failedCount,
											'woocommerce-gateway-stripe'
										),
										failedCount
									) }
								</span>
							</>
						) }
					</span>
				</div>
				{ isRecording && onChangeCaptureLimit && (
					<span className="wc-stripe-diagnostics-toolbar__limit">
						{ createInterpolateElement(
							/* translators: <select /> is a dropdown for the number of orders before auto-off triggers. */
							__(
								'Auto-off after <select /> orders',
								'woocommerce-gateway-stripe'
							),
							{
								select: (
									<select
										className="wc-stripe-diagnostics-toolbar__limit-select"
										value={ String( captureLimit ) }
										onChange={ ( event ) =>
											onChangeCaptureLimit(
												Number( event.target.value )
											)
										}
										aria-label={ __(
											'Auto-off capture limit',
											'woocommerce-gateway-stripe'
										) }
									>
										{ captureLimitPresets.map(
											( preset ) => (
												<option
													key={ preset }
													value={ String( preset ) }
												>
													{ preset }
												</option>
											)
										) }
									</select>
								),
							}
						) }
					</span>
				) }
			</div>
			<div
				className="wc-stripe-diagnostics-toolbar__row wc-stripe-diagnostics-toolbar__row--actions"
				role="group"
				aria-label={ __(
					'Trace list actions',
					'woocommerce-gateway-stripe'
				) }
			>
				<div
					className="wc-stripe-diagnostics-segmented"
					role="group"
					aria-label={ __(
						'Filter traces',
						'woocommerce-gateway-stripe'
					) }
				>
					<button
						type="button"
						aria-pressed={ filter === FILTER_ALL }
						className={ classNames(
							'wc-stripe-diagnostics-segmented__option',
							{
								'is-active': filter === FILTER_ALL,
							}
						) }
						onClick={ () => onFilterChange( FILTER_ALL ) }
					>
						{ __( 'All', 'woocommerce-gateway-stripe' ) }
					</button>
					<button
						type="button"
						aria-pressed={ filter === FILTER_FAILED }
						className={ classNames(
							'wc-stripe-diagnostics-segmented__option',
							{
								'is-active': filter === FILTER_FAILED,
							}
						) }
						onClick={ () => onFilterChange( FILTER_FAILED ) }
					>
						{ __( 'Failed only', 'woocommerce-gateway-stripe' ) }
					</button>
				</div>
				<div className="wc-stripe-diagnostics-toolbar__actions">
					<Button
						variant="secondary"
						onClick={ onCopy }
						isBusy={ isCopying }
						disabled={ isCopying || isClearing || totalCount === 0 }
					>
						{ copyLabel }
					</Button>
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () => setIsConfirmingClear( true ) }
						isBusy={ isClearing }
						disabled={ isCopying || isClearing || totalCount === 0 }
					>
						{ __( 'Clear', 'woocommerce-gateway-stripe' ) }
					</Button>
				</div>
			</div>
			{ isRecording && (
				<div
					className="wc-stripe-diagnostics-toolbar__progress"
					role="progressbar"
					aria-valuemin={ 0 }
					aria-valuemax={ captureLimit }
					aria-valuenow={ Math.min( totalCount, captureLimit ) }
					aria-label={ __(
						'Capture progress',
						'woocommerce-gateway-stripe'
					) }
				>
					<div
						className="wc-stripe-diagnostics-toolbar__progress-bar"
						style={ { width: `${ progressPct }%` } }
					/>
				</div>
			) }
			{ isConfirmingClear && (
				<ConfirmationModal
					onRequestClose={ () => setIsConfirmingClear( false ) }
					title={ __(
						'Clear stored diagnostics traces?',
						'woocommerce-gateway-stripe'
					) }
					actions={
						<>
							<Button
								onClick={ () => setIsConfirmingClear( false ) }
								isSecondary
							>
								{ __( 'Cancel', 'woocommerce-gateway-stripe' ) }
							</Button>
							<Button
								onClick={ handleClearConfirm }
								isPrimary
								isDestructive
							>
								{ __(
									'Clear traces',
									'woocommerce-gateway-stripe'
								) }
							</Button>
						</>
					}
				>
					<p>
						{ sprintf(
							/* translators: %d: number of stored traces about to be deleted */
							_n(
								'This will permanently delete %d stored trace. This cannot be undone.',
								'This will permanently delete %d stored traces. This cannot be undone.',
								totalCount,
								'woocommerce-gateway-stripe'
							),
							totalCount
						) }
					</p>
				</ConfirmationModal>
			) }
		</div>
	);
};

export default DiagnosticsTraceToolbar;
export { FILTER_ALL, FILTER_FAILED };
