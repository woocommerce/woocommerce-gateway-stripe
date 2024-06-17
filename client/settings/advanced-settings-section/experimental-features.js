import { __ } from '@wordpress/i18n';
import { createInterpolateElement, useState } from '@wordpress/element';
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
	const [ isLegacyEnabled, setIsLegacyEnabled ] = useState( ! isUpeEnabled );
	const { isSaving } = useSettings();
	const savingError = useGetSavingError();
	const { setIsUpeEnabledLocally } = useContext( UpeToggleContext );
	const isUpeEnabledBeforeSaving = useRef( isUpeEnabled );
	const headingRef = useRef( null );
	const setIsUpeEnabledBeforeSaving = useCallback( ( value ) => {
		isUpeEnabledBeforeSaving.current = value;
	}, [] );
	const hasSavedSettings = useRef( false );
	const setHasSavedSettings = useCallback( ( value ) => {
		hasSavedSettings.current = value;
	}, [] );

	useEffect( () => {
		if ( ! headingRef.current ) {
			return;
		}

		const { highlight } = getQuery();
		if ( highlight === 'enable-upe' ) {
			headingRef.current.focus();
		}
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
				}
				// It needs to retrieve the available payment methods based on
				// the new UPE enabled status in order to have the payment methods
				// list up-to-date.
				dispatch( STORE_NAME ).invalidateResolutionForStoreSelector(
					'getSettings'
				);
				setIsUpeEnabledLocally( isUpeEnabled );
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

	// The checkbox control uses the opposite value of the UPE state since 8.1.0.
	const setIsLegacyExperienceEnabled = ( value ) => {
		setIsUpeEnabled( ! value );
		setIsLegacyEnabled( value );
	};

	return (
		<>
			<h4 ref={ headingRef } tabIndex="-1">
				{ __(
					'Legacy checkout experience',
					'woocommerce-gateway-stripe'
				) }
			</h4>
			<CheckboxControl
				data-testid="legacy-checkout-experience-checkbox"
				label={ __(
					'Enable the legacy checkout experience',
					'woocommerce-gateway-stripe'
				) }
				help={ createInterpolateElement(
					__(
						'If you enable this, your store may stop processing payments in the near future as Stripe will no longer support this integration. <learnMoreLink>Learn more</learnMoreLink>.<newLineElement />Going back to the legacy experience? Reach out to us through our <feedbackLink>feedback form</feedbackLink> or <supportLink>support channel</supportLink>.',
						'woocommerce-gateway-stripe'
					),
					{
						feedbackLink: (
							<ExternalLink href="https://woocommerce.survey.fm/woocommerce-stripe-upe-opt-out-survey" />
						),
						learnMoreLink: (
							<ExternalLink href="https://woocommerce.com/document/stripe/admin-experience/new-checkout-experience/" />
						),
						supportLink: (
							<ExternalLink href="https://woocommerce.com/my-account/create-a-ticket?select=18627" />
						),
						newLineElement: <br />,
					}
				) }
				checked={ isLegacyEnabled }
				onChange={ setIsLegacyExperienceEnabled }
			/>
		</>
	);
};

export default ExperimentalFeatures;
