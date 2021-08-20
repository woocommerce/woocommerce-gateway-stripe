/**
 * External dependencies
 */
import { TabPanel } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import { PaymentSettings } from '../payment-settings/index';
import { PaymentMethods } from '../payment-methods/index';

const SettingsTabPanel = () => (
	<TabPanel
		className="my-tab-panel"
		initialTabName="paymentMethods"
		tabs={ [
			{
				name: 'paymentMethods',
				title: 'Payment methods',
				className: 'tab-one',
			},
			{
				name: 'paymentSettings',
				title: 'Settings',
				className: 'tab-two',
			},
		] }
	>
		{ ( tab ) =>
			tab.name === 'paymentSettings' ? (
				<PaymentSettings />
			) : (
				<PaymentMethods />
			)
		}
	</TabPanel>
);

export default SettingsTabPanel;
