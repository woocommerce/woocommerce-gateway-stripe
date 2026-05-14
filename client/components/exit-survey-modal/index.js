import React, { useCallback, useEffect, useState } from 'react';
import StripeLogo from './stripe-logo';
import { Modal, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import './style.scss';

const SURVEY_BASE_URL = 'https://woocommerce.survey.fm/stripe-exit-survey';
const SURVEY_IFRAME_ORIGIN = 'https://woocommerce.survey.fm';
const COOLDOWN_DAYS = 7;
const MIN_IFRAME_HEIGHT = 600;

/**
 * Crowdsignal question position mapping for hidden fields.
 * Update these if the survey question order changes.
 */
const QUESTION_PARAMS = {
	stripeAccountId: 'q_2_text',
	wcStoreId: 'q_3_text',
	pluginVersion: 'q_4_text',
	wcVersion: 'q_5_text',
	wpVersion: 'q_6_text',
	trigger: 'q_7_text',
};

/**
 * Check whether the survey cooldown is still active.
 *
 * @param {string|null} lastShown ISO 8601 timestamp or null.
 * @return {boolean} True if the survey should be suppressed.
 */
export function isCooldownActive( lastShown ) {
	if ( ! lastShown ) {
		return false;
	}

	const lastShownDate = new Date( lastShown );
	if ( isNaN( lastShownDate.getTime() ) ) {
		return false;
	}

	const cooldownEnd = new Date(
		lastShownDate.getTime() + COOLDOWN_DAYS * 24 * 60 * 60 * 1000
	);
	return new Date() < cooldownEnd;
}

/**
 * Build the Crowdsignal iframe URL with hidden field parameters.
 *
 * @param {Object} surveyParams Hidden field values from PHP.
 * @param {string} trigger      The trigger type ('deactivate' or 'disable').
 * @return {string} The full iframe URL.
 */
export function buildSurveyUrl( surveyParams, trigger ) {
	const url = new URL( SURVEY_BASE_URL );
	url.searchParams.set( 'iframe', '1' );
	url.searchParams.set(
		QUESTION_PARAMS.stripeAccountId,
		surveyParams.stripe_account_id || ''
	);
	url.searchParams.set(
		QUESTION_PARAMS.wcStoreId,
		surveyParams.wc_store_id || ''
	);
	url.searchParams.set(
		QUESTION_PARAMS.pluginVersion,
		surveyParams.plugin_version || ''
	);
	url.searchParams.set(
		QUESTION_PARAMS.wcVersion,
		surveyParams.wc_version || ''
	);
	url.searchParams.set(
		QUESTION_PARAMS.wpVersion,
		surveyParams.wp_version || ''
	);
	url.searchParams.set( QUESTION_PARAMS.trigger, trigger );

	return url.toString();
}

/**
 * Exit survey modal component.
 *
 * Displays a Crowdsignal survey in an iframe inside a WordPress Modal.
 * Persists a 7-day cooldown via REST on close.
 *
 * @param {Object}   props                Props.
 * @param {string}   props.trigger        'deactivate' or 'disable'.
 * @param {Function} props.onRequestClose Called after the modal is closed and cooldown is persisted.
 * @param {Object}   props.surveyParams   Hidden field values from wp_localize_script.
 */
const ExitSurveyModal = ( { trigger, onRequestClose, surveyParams } ) => {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ iframeHeight, setIframeHeight ] = useState( MIN_IFRAME_HEIGHT );

	// Listen for iframe resize messages from Crowdsignal.
	useEffect( () => {
		const handleMessage = ( event ) => {
			if (
				event.origin !== SURVEY_IFRAME_ORIGIN ||
				event.data?.type !== 'embed-size' ||
				! event.data?.height
			) {
				return;
			}

			const height = Number( event.data.height );
			if ( ! Number.isFinite( height ) ) {
				return;
			}
			setIframeHeight( Math.max( height, MIN_IFRAME_HEIGHT ) );
		};

		window.addEventListener( 'message', handleMessage );
		return () => window.removeEventListener( 'message', handleMessage );
	}, [] );

	const handleClose = useCallback( async () => {
		// Persist cooldown — best-effort with a timeout so we don't block deactivation.
		try {
			await Promise.race( [
				apiFetch( {
					path: '/wc/v3/wc_stripe/exit-survey/dismiss',
					method: 'POST',
				} ),
				new Promise( ( resolve ) => setTimeout( resolve, 1500 ) ),
			] );
		} catch {
			// Cooldown persistence failed — proceed anyway.
		}

		onRequestClose();
	}, [ onRequestClose ] );

	const surveyUrl = buildSurveyUrl( surveyParams, trigger );

	return (
		<Modal
			title={
				<span className="wc-stripe-exit-survey-modal__title">
					<StripeLogo />
					{ __(
						'Stripe for WooCommerce',
						'woocommerce-gateway-stripe'
					) }
				</span>
			}
			className="wc-stripe-exit-survey-modal"
			onRequestClose={ handleClose }
			shouldCloseOnClickOutside={ false }
		>
			<div className="wc-stripe-exit-survey-modal__content">
				{ isLoading && (
					<div className="wc-stripe-exit-survey-modal__spinner">
						<Spinner />
					</div>
				) }
				<iframe
					title={ __( 'Exit Survey', 'woocommerce-gateway-stripe' ) }
					src={ surveyUrl }
					className="wc-stripe-exit-survey-modal__iframe"
					sandbox="allow-scripts allow-forms allow-same-origin allow-popups allow-popups-to-escape-sandbox"
					style={ {
						height: `${ iframeHeight }px`,
						opacity: isLoading ? 0 : 1,
					} }
					onLoad={ () => setIsLoading( false ) }
				/>
			</div>
		</Modal>
	);
};

export default ExitSurveyModal;
