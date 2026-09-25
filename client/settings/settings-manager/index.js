/* global wc_stripe_settings_params */
import React, { useContext, useRef, useState } from 'react';
import { getQuery, updateQueryString } from '@woocommerce/navigation';
import styled from '@emotion/styled';
import SettingsLayout from '../settings-layout';
import PaymentSettingsPanel from '../payment-settings';
import PaymentMethodsPanel from '../payment-methods';
import AgenticCommerceSection from '../agentic-commerce';
import SaveSettingsSection from '../save-settings-section';
import { useEnabledPaymentMethodIds, useSettings } from '../../data';
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { useAccount } from 'wcstripe/data/account';
import OCToggleContext from 'wcstripe/settings/oc-toggle/context';
import { getPromotionalBannerType } from 'wcstripe/settings/payment-settings/promotional-banner/get-promotional-banner-type';
import ExitSurveyModal, {
	isCooldownActive,
} from 'wcstripe/components/exit-survey-modal';

const StyledTabPanel = styled( TabPanel )`
	.components-tab-panel__tabs {
		border-bottom: 1px solid #c3c4c7;
		margin-bottom: 32px;
	}
`;

const getTabs = ( isAgenticCommerceEnabled ) => [
	{
		name: 'methods',
		title: __( 'Payment Methods', 'woocommerce-gateway-stripe' ),
	},
	{
		name: 'settings',
		title: __( 'Settings', 'woocommerce-gateway-stripe' ),
	},
	...( isAgenticCommerceEnabled
		? [
				{
					name: 'agentic-commerce',
					title: __(
						'Agentic Commerce',
						'woocommerce-gateway-stripe'
					),
				},
		  ]
		: [] ),
];

const SettingsManager = () => {
	const isAgenticCommerceEnabled =
		wc_stripe_settings_params?.is_agentic_commerce_enabled; // eslint-disable-line camelcase

	const agenticSaveRef = useRef( null );

	const { settings, isLoading } = useSettings();
	const [ initialSettings, setInitialSettings ] = useState( settings );
	const { data } = useAccount();
	const { isOCEnabled, setIsOCEnabled } = useContext( OCToggleContext );
	const [ enabledPaymentMethodIds ] = useEnabledPaymentMethodIds();
	const promotionalBannerType = getPromotionalBannerType(
		data,
		isOCEnabled,
		enabledPaymentMethodIds
	);
	const initialBannerState = promotionalBannerType !== null;
	const [ showPromotionalBanner, setShowPromotionalBanner ] =
		useState( initialBannerState );

	useEffect( () => {
		if ( isLoading && Object.keys( settings ).length > 0 ) {
			setInitialSettings( settings );
		}
	}, [ isLoading, settings ] );

	const [ showExitSurvey, setShowExitSurvey ] = useState( false );

	const onSettingsSave = () => {
		// Show exit survey if Stripe was just disabled.
		if (
			initialSettings.is_stripe_enabled &&
			! settings.is_stripe_enabled &&
			// eslint-disable-next-line camelcase
			typeof wc_stripe_settings_params !== 'undefined' &&
			! isCooldownActive(
				// eslint-disable-next-line camelcase
				wc_stripe_settings_params.exit_survey_last_shown
			)
		) {
			setShowExitSurvey( true );
		}

		setInitialSettings( settings );
	};

	const onSaveChanges = ( key, value ) => {
		setInitialSettings( {
			...initialSettings,
			[ key ]: value,
		} );
	};

	// This grabs the "panel" URL query string value to allow for opening a specific tab.
	const { panel } = getQuery();

	const updatePanelUri = ( tabName ) => {
		updateQueryString( { panel: tabName }, '/', getQuery() );
	};

	const tabs = getTabs( isAgenticCommerceEnabled );

	const getInitialTab = () => {
		const requestedTab = tabs.find( ( { name } ) => name === panel );

		return requestedTab ? requestedTab.name : 'methods';
	};

	return (
		<SettingsLayout>
			{ showExitSurvey && (
				<ExitSurveyModal
					trigger="settings_disable"
					surveyParams={
						// eslint-disable-next-line camelcase
						wc_stripe_settings_params
					}
					onRequestClose={ () => setShowExitSurvey( false ) }
				/>
			) }
			<StyledTabPanel
				className="wc-stripe-account-settings-panel"
				initialTabName={ getInitialTab() }
				tabs={ tabs }
				onSelect={ updatePanelUri }
			>
				{ ( tab ) => (
					<div data-testid={ `${ tab.name }-tab` }>
						{ tab.name === 'settings' && (
							<PaymentSettingsPanel
								showPromotionalBanner={ showPromotionalBanner }
								setShowPromotionalBanner={
									setShowPromotionalBanner
								}
								promotionalBannerType={ promotionalBannerType }
								isOCEnabled={ isOCEnabled }
								setIsOCEnabled={ setIsOCEnabled }
							/>
						) }
						{ tab.name === 'methods' && (
							<PaymentMethodsPanel
								onSaveChanges={ onSaveChanges }
								showPromotionalBanner={ showPromotionalBanner }
								setShowPromotionalBanner={
									setShowPromotionalBanner
								}
								promotionalBannerType={ promotionalBannerType }
								isOCEnabled={ isOCEnabled }
								setIsOCEnabled={ setIsOCEnabled }
							/>
						) }
						{ tab.name === 'agentic-commerce' && (
							<AgenticCommerceSection ref={ agenticSaveRef } />
						) }
						<SaveSettingsSection
							onSettingsSave={ onSettingsSave }
							agenticSaveRef={
								tab.name === 'agentic-commerce'
									? agenticSaveRef
									: undefined
							}
						/>
					</div>
				) }
			</StyledTabPanel>
		</SettingsLayout>
	);
};

export default SettingsManager;
