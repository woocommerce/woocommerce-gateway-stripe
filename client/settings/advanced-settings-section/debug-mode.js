import { __ } from '@wordpress/i18n';
import React from 'react';
import { CheckboxControl } from '@wordpress/components';
import { useDebugLog } from 'wcstripe/data';

const DebugMode = () => {
	const [ isLoggingChecked, setIsLoggingChecked ] = useDebugLog();

	return (
		<>
			<h4>{ __( 'Debug mode', 'woocommerce-gateway-stripe' ) }</h4>
			<CheckboxControl
				data-testid="logging-checkbox"
				label={ __(
					'Log debug messages',
					'woocommerce-gateway-stripe'
				) }
				help={ __(
					'When enabled, payment debug logs will be saved to WooCommerce > Status > Logs.',
					'woocommerce-gateway-stripe'
				) }
				checked={ isLoggingChecked }
				onChange={ setIsLoggingChecked }
			/>
		</>
	);
};

export default DebugMode;
