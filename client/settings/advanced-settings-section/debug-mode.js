/**
 * External dependencies
 */
import React, { useEffect, useRef } from 'react';
import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useDevMode } from './data-mock';
import { useDebugLog } from 'wcstripe/data';

const DebugMode = () => {
	const isDevModeEnabled = useDevMode();
	const [ isLoggingChecked, setIsLoggingChecked ] = useDebugLog();
	const headingRef = useRef( null );

	useEffect( () => {
		if ( ! headingRef.current ) {
			return;
		}

		headingRef.current.focus();
	}, [] );

	return (
		<>
			<h4 ref={ headingRef } tabIndex="-1">
				{ __( 'Debug mode', 'woocommerce-gateway-stripe' ) }
			</h4>
			<CheckboxControl
				data-testid="logging-checkbox"
				label={
					isDevModeEnabled
						? __(
								'Dev mode is active so logging is on by default.',
								'woocommerce-gateway-stripe'
						  )
						: __(
								'Log error messages',
								'woocommerce-gateway-stripe'
						  )
				}
				help={ __(
					'When enabled, payment error logs will be saved to WooCommerce > Status > Logs.',
					'woocommerce-gateway-stripe'
				) }
				disabled={ isDevModeEnabled }
				checked={ isDevModeEnabled || isLoggingChecked }
				onChange={ setIsLoggingChecked }
			/>
		</>
	);
};

export default DebugMode;
