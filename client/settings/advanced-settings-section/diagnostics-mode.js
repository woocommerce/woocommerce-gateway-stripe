import React from 'react';
import DiagnosticsTraces from './diagnostics-traces';
import { __ } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';
import {
	useDiagnosticsMode,
	useDiagnosticsCaptureLimit,
	useDiagnosticsCaptureLimitPresets,
} from 'wcstripe/data';

const DiagnosticsMode = () => {
	const [ isDiagnosticsChecked, setIsDiagnosticsChecked ] =
		useDiagnosticsMode();
	const [ captureLimit, setCaptureLimit ] = useDiagnosticsCaptureLimit();
	const captureLimitPresets = useDiagnosticsCaptureLimitPresets();

	return (
		<>
			<div className="wc-stripe-diagnostics-header">
				<div className="wc-stripe-diagnostics-header__text">
					<h4>
						{ __(
							'Checkout diagnostics',
							'woocommerce-gateway-stripe'
						) }
					</h4>
					<p>
						{ __(
							'Records structured traces of checkout sessions so support can diagnose issues. Turns off automatically once the capture limit is reached.',
							'woocommerce-gateway-stripe'
						) }
					</p>
				</div>
				<ToggleControl
					label={ __(
						'Capture checkout diagnostics',
						'woocommerce-gateway-stripe'
					) }
					checked={ isDiagnosticsChecked }
					onChange={ setIsDiagnosticsChecked }
				/>
			</div>
			<DiagnosticsTraces
				isRecording={ isDiagnosticsChecked }
				captureLimit={ Number( captureLimit ) }
				captureLimitPresets={ captureLimitPresets }
				onChangeCaptureLimit={ setCaptureLimit }
			/>
		</>
	);
};

export default DiagnosticsMode;
