import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { CheckboxControl, ExternalLink } from '@wordpress/components';
import { getQuery, updateQueryString } from '@woocommerce/navigation';
import React, { useCallback, useContext, useEffect, useRef } from 'react';
import { useGetSavingError, useIsUpeEnabled, useSettings } from '../../data';
import UpeToggleContext from '../upe-toggle/context';
import { STORE_NAME } from '../../data/constants';

const ExperimentalFeatures = () => {
	const dispatch = useDispatch();
	const [ isUpeEnabled, setIsUpeEnabled ] = useIsUpeEnabled();
	const { isSaving } = useSettings();
	const savingError = useGetSavingError();
	const { setIsUpeEnabledLocally } = useContext( UpeToggleContext );
	const isUpeEnabledBeforeSaving = useRef( isUpeEnabled );
	const setIsUpeEnabledBeforeSaving = useCallback( ( value ) => {
		isUpeEnabledBeforeSaving.current = value;
	}, [] );
	const hasSavedSettings = useRef( false );
	const setHasSavedSettings = useCallback( ( value ) => {
		hasSavedSettings.current = value;
	}, [] );

	useEffect( () => {
		if ( isSaving && ! hasSavedSettings.current ) {
			setHasSavedSettings( true );
		} else if ( ! isSaving && hasSavedSettings.current && ! savingError ) {
			if ( isUpeEnabled !== isUpeEnabledBeforeSaving.current ) {
				if ( isUpeEnabled ) {
					dispatch( 'core/notices' ).createSuccessNotice(
						__(
							'✅ New checkout experience enabled.',
							'woocommerce-gateway-stripe'
						),
						{
							actions: [
								{
									label: __(
										'Review accepted payment methods',
										'woocommerce-gateway-stripe'
									),
									onClick: () => {
										updateQueryString(
											{ panel: 'methods' },
											'/',
											getQuery()
										);
										// It doesn't seem to be possible to programatically switch the tab.
										// For that reason, we need to reload so the user can review their PMs.
										window.location.reload();
									},
								},
							],
						}
					);
				} else {
					// Since it won't reload, it needs to retrieve the available
					// payment methods based on the new UPE enabled status
					dispatch( STORE_NAME ).invalidateResolutionForStoreSelector(
						'getSettings'
					);
					setIsUpeEnabledLocally( isUpeEnabled );
				}
			}

			setHasSavedSettings( false );
			setIsUpeEnabledBeforeSaving( isUpeEnabled );
		}
	}, [
		isSaving,
		isUpeEnabled,
		savingError,
		dispatch,
		setHasSavedSettings,
		setIsUpeEnabledBeforeSaving,
		setIsUpeEnabledLocally,
	] );

	return (
		<>
			<h4 tabIndex="-1">
				{ __( 'Experimental features', 'woocommerce-gateway-stripe' ) }
			</h4>
			<CheckboxControl
				data-testid="new-checkout-experience-checkbox"
				label={ __(
					'Try the new checkout experience (early access)',
					'woocommerce-gateway-stripe'
				) }
				help={ createInterpolateElement(
					__(
						'Get early access to a new, smarter payment experience on checkout and let us know what you think by <feedbackLink>submitting your feedback</feedbackLink>. We recommend this feature for experienced merchants as the functionality is currently limited. <learnMoreLink>Learn more</learnMoreLink>',
						'woocommerce-gateway-stripe'
					),
					{
						feedbackLink: (
							<ExternalLink href="https://woocommerce.survey.fm/woocommerce-stripe-upe-opt-out-survey" />
						),
						learnMoreLink: (
							<ExternalLink href="https://woocommerce.com/document/stripe/#new-checkout-experience" />
						),
					}
				) }
				checked={ isUpeEnabled }
				onChange={ setIsUpeEnabled }
			/>
		</>
	);
};

export default ExperimentalFeatures;
