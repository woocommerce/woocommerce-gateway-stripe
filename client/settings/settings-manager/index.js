/**
 * External dependencies
 */
import React from 'react';
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getQuery } from '@woocommerce/navigation';
import { css } from '@linaria/core';

/**
 * Internal dependencies
 */
import SettingsLayout from '../settings-layout';
import PaymentSettingsPanel from '../payment-settings';
import PaymentMethodsPanel from '../payment-methods';
import SaveSettingsSection from '../save-settings-section';

const TabPanelStyles = css`
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
	// This grabs the "panel" URL query string value to allow for opening a specific tab.
	const { panel } = getQuery();

	return (
		<SettingsLayout>
			<TabPanel
				className={ TabPanelStyles }
				initialTabName={ panel === 'settings' ? 'settings' : 'methods' }
				tabs={ TABS_CONTENT }
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
			</TabPanel>
		</SettingsLayout>
	);
};

export default SettingsManager;
