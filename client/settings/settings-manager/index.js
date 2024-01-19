import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import React, { useState } from 'react';
import { TabPanel } from '@wordpress/components';
import { getQuery, updateQueryString } from '@woocommerce/navigation';
import styled from '@emotion/styled';
import { isEmpty, isEqual } from 'lodash';
import SettingsLayout from '../settings-layout';
import PaymentSettingsPanel from '../payment-settings';
import PaymentMethodsPanel from '../payment-methods';
import SaveSettingsSection from '../save-settings-section';
import { useSettings } from '../../data';
import useConfirmNavigation from 'utils/use-confirm-navigation';

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

	useEffect( () => {
		if ( isLoading && ! isEmpty( settings ) ) {
			setInitialSettings( settings );
		}
	}, [ isLoading, settings ] );

	const onSettingsSave = () => {
		setInitialSettings( settings );
	};

	const onSaveChanges = ( key, data ) => {
		setInitialSettings( {
			...initialSettings,
			[ key ]: data,
		} );
	};

	const isPristine =
		! isEmpty( initialSettings ) && isEqual( initialSettings, settings );
	const displayPrompt = ! isPristine;
	const confirmationNavigationCallback = useConfirmNavigation(
		displayPrompt
	);

	useEffect( confirmationNavigationCallback, [
		displayPrompt,
		confirmationNavigationCallback,
	] );

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
							<PaymentSettingsPanel />
						) : (
							<PaymentMethodsPanel
								onSaveChanges={ onSaveChanges }
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
