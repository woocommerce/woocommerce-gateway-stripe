/* global wcStripeExitSurveyParams */
import React, { useCallback, useEffect, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import ExitSurveyModal, {
	isCooldownActive,
} from 'wcstripe/components/exit-survey-modal';

/**
 * Detects Stripe gateway disable on the WC core Payments settings page
 * via apiFetch middleware. The WC core page dispatches the toggle via
 * apiFetch to admin-ajax.php with form-encoded body containing
 * action=woocommerce_toggle_gateway_enabled&gateway_id=stripe.
 *
 * Shows the exit survey after the disable request completes successfully.
 */
const PaymentsPageSurvey = () => {
	const [ showSurvey, setShowSurvey ] = useState( false );

	useEffect( () => {
		apiFetch.use( ( options, next ) => {
			const method = ( options.method || 'GET' ).toUpperCase();
			if ( method !== 'POST' ) {
				return next( options );
			}

			// The body can be a string, URLSearchParams, or FormData.
			const body = options.body;
			let bodyStr = '';
			if ( body instanceof URLSearchParams ) {
				bodyStr = body.toString();
			} else if ( typeof body === 'string' ) {
				bodyStr = body;
			}

			// Match the gateway toggle action for Stripe.
			if (
				! bodyStr.includes(
					'action=woocommerce_toggle_gateway_enabled'
				) ||
				! bodyStr.includes( 'gateway_id=stripe' )
			) {
				return next( options );
			}

			// Let the request proceed, then check the response.
			const result = next( options );
			result.then( ( response ) => {
				// WC returns { success: true, data: false } for disable.
				if (
					response &&
					response.success &&
					response.data === false &&
					! isCooldownActive(
						wcStripeExitSurveyParams.exit_survey_last_shown
					)
				) {
					setShowSurvey( true );
				}
			} );

			return result;
		} );
	}, [] );

	const handleSurveyClose = useCallback( () => {
		// Update in-memory cooldown to prevent re-show if disable is retried.
		wcStripeExitSurveyParams.exit_survey_last_shown =
			new Date().toISOString();
		setShowSurvey( false );
	}, [] );

	if ( ! showSurvey ) {
		return null;
	}

	return (
		<ExitSurveyModal
			trigger="payments_page_disable"
			surveyParams={ wcStripeExitSurveyParams }
			onRequestClose={ handleSurveyClose }
		/>
	);
};

export default PaymentsPageSurvey;
