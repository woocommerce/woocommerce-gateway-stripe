import { __ } from '@wordpress/i18n';
import Chip from 'wcstripe/components/chip';

/**
 * Generates a status chip.
 *
 * @param {Object} props           The component props.
 * @param {string} props.label     The label/type of the status.
 * @param {string} props.text      The text of the status. The actual status message. eg "Enabled".
 * @param {string} props.color     The color of the status.
 * @param {JSX.Element} props.icon The icon of the status.
 * @return {JSX.Element}           The status chip.
 */
const Status = ( { label, text, color, icon } ) => {
	return (
		<div className="wcstripe-status">
			<span className="wcstripe-status-text">{ label }</span>
			<Chip text={ text } color={ color } icon={ icon } />
		</div>
	);
};

/**
 * The AccountStatusPanel component.
 *
 * @param {Object} props           The component props.
 * @param {boolean} props.testMode Indicates whether the component is for test mode.
 * @return {JSX.Element}           The rendered AccountStatusPanel component.
 */
const AccountStatusPanel = ( { testMode } ) => {
	const accountStatus = testMode ? 'Connected' : 'Disconnected';
	const webhookStatus = testMode ? 'Disabled' : 'Configured';
	const accountColor = testMode ? 'green' : 'yellow';
	const webhookColor = testMode ? 'red' : 'green';
	return (
		<div className="wcstripe-account-status-panel">
			<Status
				label={ __( 'Account', 'woocommerce-gateway-stripe' ) }
				text={ accountStatus }
				color={ accountColor }
			/>
			<Status
				label={ __( 'Webhooks', 'woocommerce-gateway-stripe' ) }
				text={ webhookStatus }
				color={ webhookColor }
			/>
		</div>
	);
};

export default AccountStatusPanel;
