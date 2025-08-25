/* global wc_stripe_settings_params */
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import React, { useContext, useState } from 'react';
import { getQuery, updateQueryString } from '@woocommerce/navigation';
import styled from '@emotion/styled';
import { isEmpty } from 'lodash';
import { TabPanel } from '@wordpress/components';
import SettingsLayout from '../settings-layout';
import PaymentSettingsPanel from '../payment-settings';
import PaymentMethodsPanel from '../payment-methods';
import SaveSettingsSection from '../save-settings-section';
import { useEnabledPaymentMethodIds, useSettings } from '../../data';
import { useAccount } from 'wcstripe/data/account';
import OCToggleContext from 'wcstripe/settings/oc-toggle/context';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';
import { getPromotionalBannerType } from 'wcstripe/settings/payment-settings/promotional-banner/get-promotional-banner-type';
import {
	BNPL_PROMOTION_BANNER,
	OC_PROMOTION_BANNER,
} from 'wcstripe/settings/payment-settings/constants';

const StyledTabPanel = styled( TabPanel )`
	.components-tab-panel__tabs {
		border-bottom: 1px solid #c3c4c7;
		margin-bottom: 32px;
	}
`;

const TABS_CONTENT = [
	{
		name: 'methods',
		title: __( 'Payment Methods', 'woocommerce-gateway-stripe' ),
	},
	{
		name: 'settings',
		title: __( 'Settings', 'woocommerce-gateway-stripe' ),
	},
];

const SettingsManager = () => {
	const { settings, isLoading } = useSettings();
	const [ initialSettings, setInitialSettings ] = useState( settings );
	const { data } = useAccount();
	const { isOCEnabled, setIsOCEnabled } = useContext( OCToggleContext );
	const { isUpeEnabled, setIsUpeEnabled } = useContext( UpeToggleContext );
	const [ enabledPaymentMethodIds ] = useEnabledPaymentMethodIds();
	const promotionalBannerType = getPromotionalBannerType(
		data,
		isUpeEnabled,
		isOCEnabled,
		enabledPaymentMethodIds
	);
	let initialBannerState;
	if (
		promotionalBannerType === BNPL_PROMOTION_BANNER &&
		// eslint-disable-next-line camelcase
		wc_stripe_settings_params?.show_bnpl_promotional_banner === '1'
	) {
		initialBannerState = true;
	}
	if (
		promotionalBannerType === OC_PROMOTION_BANNER &&
		// eslint-disable-next-line camelcase
		wc_stripe_settings_params?.show_oc_promotional_banner === '1'
	) {
		initialBannerState = true;
	}
	const [ showPromotionalBanner, setShowPromotionalBanner ] = useState(
		initialBannerState
	);

	useEffect( () => {
		if ( isLoading && ! isEmpty( settings ) ) {
			setInitialSettings( settings );
		}
	}, [ isLoading, settings ] );

	const onSettingsSave = () => {
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

	return (
		<SettingsLayout>
			<StyledTabPanel
				className="wc-stripe-account-settings-panel"
				initialTabName={ panel === 'settings' ? 'settings' : 'methods' }
				tabs={ TABS_CONTENT }
				onSelect={ updatePanelUri }
			>
				{ ( tab ) => (
					<div data-testid={ `${ tab.name }-tab` }>
						{ tab.name === 'settings' ? (
							<PaymentSettingsPanel
								showPromotionalBanner={ showPromotionalBanner }
								setShowPromotionalBanner={
									setShowPromotionalBanner
								}
								promotionalBannerType={ promotionalBannerType }
								isOCEnabled={ isOCEnabled }
								setIsOCEnabled={ setIsOCEnabled }
								setIsUpeEnabled={ setIsUpeEnabled }
							/>
						) : (
							<PaymentMethodsPanel
								onSaveChanges={ onSaveChanges }
								showPromotionalBanner={ showPromotionalBanner }
								setShowPromotionalBanner={
									setShowPromotionalBanner
								}
								promotionalBannerType={ promotionalBannerType }
								isOCEnabled={ isOCEnabled }
								setIsOCEnabled={ setIsOCEnabled }
								setIsUpeEnabled={ setIsUpeEnabled }
							/>
						) }
						<SaveSettingsSection
							onSettingsSave={ onSettingsSave }
						/>
					</div>
				) }
			</StyledTabPanel>
		</SettingsLayout>
	);
};

export default SettingsManager;
