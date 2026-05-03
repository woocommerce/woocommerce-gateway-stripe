import React from 'react';
import DiagnosticsTraces from './diagnostics-traces';
import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';
import { useDiagnosticsMode } from 'wcstripe/data';

const DiagnosticsMode = () => {
	const [ isDiagnosticsChecked, setIsDiagnosticsChecked ] =
		useDiagnosticsMode();

	return (
		<>
			<h4>
				{ __( 'Checkout diagnostics', 'woocommerce-gateway-stripe' ) }
			</h4>
			<CheckboxControl
				data-testid="diagnostics-checkbox"
				label={ __(
					'Capture checkout diagnostics',
					'woocommerce-gateway-stripe'
				) }
				help={ __(
					'When enabled, captures structured traces of checkout sessions for support diagnostics. Disable when not actively troubleshooting.',
					'woocommerce-gateway-stripe'
				) }
				checked={ isDiagnosticsChecked }
				onChange={ setIsDiagnosticsChecked }
			/>
			<DiagnosticsTraces />
		</>
	);
};

export default DiagnosticsMode;
