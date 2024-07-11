import { __ } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';
import StripeAuthDiagram from './stripe-auth-diagram';
import StripeAuthActions from './stripe-auth-actions';
import AccountStatusPanel from './account-status-panel';
import WebhookHelpText from './webhook-help-text';
import './styles.scss';

/**
 * Generate the help text for the component based on the mode.
 *
 * @param {boolean} testMode Indicates whether the component is in test mode.
 *
 * @return {string} The generated help text.
 */
const getHelpText = ( testMode ) => {
	return interpolateComponents( {
		mixedString: testMode
			? __(
					'By clicking "Connect a test account", you agree to the {{tosLink}}Terms of service.{{/tosLink}}',
					'woocommerce-gateway-stripe'
			  )
			: __(
					'By clicking "Connect an account", you agree to the {{tosLink}}Terms of service.{{/tosLink}}',
					'woocommerce-gateway-stripe'
			  ),
		components: {
			tosLink: (
				// eslint-disable-next-line jsx-a11y/anchor-has-content
				<a
					target="_blank"
					rel="noreferrer"
					href="https://wordpress.com/tos"
				/>
			),
		},
	} );
};

/**
 * Generates the heading text for the component based on the mode.
 *
 * @param {boolean} testMode Indicates whether the component is in test mode.
 *
 * @return {string} The generated help text.
 */
const getHeading = ( testMode ) => {
	return testMode
		? __( 'Connect with Stripe in test mode', 'woocommerce-gateway-stripe' )
		: __(
				'Connect with Stripe in live mode',
				'woocommerce-gateway-stripe'
		  );
};

/**
 * StripeAuthAccount component.
 *
 * @param {Object} props           The component props.
 * @param {boolean} props.testMode Indicates whether the component is in test mode.
 *
 * @return {JSX.Element} The rendered StripeAuthAccount component.
 */
const StripeAuthAccount = ( { testMode } ) => {
	return (
		<div className="woocommerce-stripe-auth">
			<StripeAuthDiagram />
			<AccountStatusPanel testMode={ testMode } />
			<h2>{ getHeading( testMode ) }</h2>
			<p>
				Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do
				eiusmod tempor incididunt ut labore et dolore magna aliqua. Nunc
				id cursus metus aliquam eleifend mi in nulla posuere.
			</p>
			<p className="woocommerce-stripe-auth__help">
				{ getHelpText( testMode ) }
			</p>
			<StripeAuthActions testMode={ testMode } />
			<WebhookHelpText testMode={ testMode } />
		</div>
	);
};

export default StripeAuthAccount;
