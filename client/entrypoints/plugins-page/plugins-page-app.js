/* global wcStripePluginsPageParams */
import React, { useCallback, useEffect, useRef, useState } from 'react';
import ExitSurveyModal, {
	isCooldownActive,
} from 'wcstripe/components/exit-survey-modal';

const PluginsPageApp = () => {
	const [ showSurvey, setShowSurvey ] = useState( false );
	const deactivateLink = useRef( null );

	const proceedWithDeactivation = useCallback( () => {
		if ( deactivateLink.current ) {
			window.location.href =
				deactivateLink.current.getAttribute( 'href' );
		}
	}, [] );

	const handleSurveyClose = useCallback( () => {
		setShowSurvey( false );
		proceedWithDeactivation();
	}, [ proceedWithDeactivation ] );

	useEffect( () => {
		const link = document.querySelector(
			'#deactivate-woocommerce-gateway-stripe'
		);

		if ( ! link ) {
			return;
		}

		deactivateLink.current = link;

		const handleClick = ( event ) => {
			// Guard against missing localized params.
			if ( typeof wcStripePluginsPageParams === 'undefined' ) {
				return;
			}

			// If cooldown is active, skip survey and let deactivation proceed normally.
			if (
				isCooldownActive(
					wcStripePluginsPageParams.exit_survey_last_shown
				)
			) {
				return;
			}

			event.preventDefault();
			setShowSurvey( true );
		};

		link.addEventListener( 'click', handleClick );
		return () => link.removeEventListener( 'click', handleClick );
	}, [] );

	if ( ! showSurvey ) {
		return null;
	}

	return (
		<ExitSurveyModal
			trigger="plugins_page_deactivate"
			surveyParams={ wcStripePluginsPageParams }
			onRequestClose={ handleSurveyClose }
		/>
	);
};

export default PluginsPageApp;
