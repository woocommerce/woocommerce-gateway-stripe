/**
 * External dependencies
 */
import { TabPanel } from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import './style.scss';
import { PaymentMethodsPanel } from '../payment-methods';
import { PaymentSettingsPanel } from '../payment-settings';

// This grabs the "panel" URL query string value to allow for opening a specific tab.
const { panel } = getQuery();

export const UPESettingsTabPanel = () => (
	<TabPanel
		className="wc-stripe-account-settings-panel"
		initialTabName={ panel === 'settings' ? 'settings' : 'methods' }
		tabs={ [
			{
				name: 'methods',
				title: 'Payment Methods',
			},
			{
				name: 'settings',
				title: 'Settings',
			},
		] }
	>
		{ ( tab ) =>
			tab.name === 'settings' ? (
				<PaymentSettingsPanel />
			) : (
				<PaymentMethodsPanel />
			)
		}
	</TabPanel>
);
