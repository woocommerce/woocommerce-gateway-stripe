import { React } from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';
import { useTestMode } from 'wcstripe/data';
import { useAccount } from 'wcstripe/data/account';

const TestModeCheckbox = () => {
	const [ isTestModeEnabled, setTestMode ] = useTestMode();
	const { data } = useAccount();
	const isLiveAccountConnected = Boolean(
		data?.oauth_connections?.live?.connected
	);

	// Test mode cannot be turned off until a live account is connected, otherwise
	// the gateway would run in live mode without live keys and break checkout.
	const isLockedToTestMode = isTestModeEnabled && ! isLiveAccountConnected;

	const handleCheckboxChange = ( isChecked ) => {
		setTestMode( isChecked );
	};

	const helpText = interpolateComponents( {
		mixedString: __(
			'Use {{testCardNumbersLink}}test card numbers{{/testCardNumbersLink}} to simulate various transactions. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
			'woocommerce-gateway-stripe'
		),
		components: {
			testCardNumbersLink: (
				// eslint-disable-next-line jsx-a11y/anchor-has-content
				<a href="https://docs.stripe.com/testing#cards" />
			),
			learnMoreLink: (
				// eslint-disable-next-line jsx-a11y/anchor-has-content
				<a href="https://woocommerce.com/document/stripe/customer-experience/testing/" />
			),
		},
	} );

	return (
		<>
			<h4>{ __( 'Test mode', 'woocommerce-gateway-stripe' ) }</h4>
			<CheckboxControl
				checked={ isTestModeEnabled }
				disabled={ isLockedToTestMode }
				onChange={ handleCheckboxChange }
				label={ __( 'Enable test mode', 'woocommerce-gateway-stripe' ) }
				help={
					<>
						{ helpText }
						{ isLockedToTestMode && (
							<>
								<br />
								{ __(
									'Connect a live Stripe account before turning off test mode.',
									'woocommerce-gateway-stripe'
								) }
							</>
						) }
					</>
				}
			/>
		</>
	);
};

export default TestModeCheckbox;
