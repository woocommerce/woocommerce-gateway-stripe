import React from 'react';
import DiagnosticsTraces from './diagnostics-traces';
import { __ } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';
import { useDiagnosticsMode } from 'wcstripe/data';

const DiagnosticsMode = () => {
	const [ isDiagnosticsChecked, setIsDiagnosticsChecked ] =
		useDiagnosticsMode();

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
							'Records structured traces of checkout sessions so support can diagnose issues. Keep this off unless actively troubleshooting.',
							'woocommerce-gateway-stripe'
						) }
					</p>
				</div>
				<ToggleControl
					data-testid="diagnostics-toggle"
					label={ __(
						'Capture checkout diagnostics',
						'woocommerce-gateway-stripe'
					) }
					hideLabelFromVision
					checked={ isDiagnosticsChecked }
					onChange={ setIsDiagnosticsChecked }
				/>
			</div>
			<DiagnosticsTraces isRecording={ isDiagnosticsChecked } />
		</>
	);
};

export default DiagnosticsMode;
