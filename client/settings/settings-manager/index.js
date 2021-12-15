import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import React, { useRef } from 'react';
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
	const initialSettings = useRef();

	useEffect( () => {
		if ( isLoading && ! isEmpty( settings ) ) {
			initialSettings.current = settings;
		}
	}, [ isLoading, settings ] );

	const pristine =
		! isEmpty( initialSettings.current ) &&
		isEqual( initialSettings.current, settings );
	const confirmationNavigationCallback = useConfirmNavigation( () => {
		if ( pristine ) {
			return;
		}

		return __(
			'There are unsaved changes on this page. Are you sure you want to leave and discard the unsaved changes?',
			'woocommerce-payments'
		);
	} );

	useEffect( confirmationNavigationCallback, [
		pristine,
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
							<PaymentMethodsPanel />
						) }
						<SaveSettingsSection />
					</div>
				) }
			</StyledTabPanel>
		</SettingsLayout>
	);
};

export default SettingsManager;
