/**
 * External dependencies
 */
import { TabPanel } from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';
import PaymentMethodsPanel from '../payment-methods';
import PaymentSettingsPanel from '../payment-settings';

const SettingsTabPanel = () => {
	// This grabs the "panel" URL query string value to allow for opening a specific tab.
	const { panel } = getQuery();

	return (
		<TabPanel
			className="wc-stripe-account-settings-panel"
			initialTabName={ panel === 'settings' ? 'settings' : 'methods' }
			tabs={ [
				{
					name: 'methods',
					title: __(
						'Payment Methods',
						'woocommerce-gateway-stripe'
					),
				},
				{
					name: 'settings',
					title: __( 'Settings', 'woocommerce-gateway-stripe' ),
				},
			] }
		>
			{ ( tab ) =>
				tab.name === 'settings' ? (
					<div data-testid="settings-tab">
						<PaymentSettingsPanel />
					</div>
				) : (
					<div data-testid="methods-tab">
						<PaymentMethodsPanel />
					</div>
				)
			}
		</TabPanel>
	);
};

export default SettingsTabPanel;
