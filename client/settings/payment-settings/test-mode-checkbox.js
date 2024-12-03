import { __ } from '@wordpress/i18n';
import { React } from 'react';
import { CheckboxControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { useTestMode } from 'wcstripe/data';

const TestModeCheckbox = () => {
	const [ isTestModeEnabled, setTestMode ] = useTestMode();

	const handleCheckboxChange = ( isChecked ) => {
		setTestMode( isChecked );
	};

	return (
		<>
			<h4>{ __( 'Test mode', 'woocommerce-gateway-stripe' ) }</h4>
			<CheckboxControl
				checked={ isTestModeEnabled }
				onChange={ handleCheckboxChange }
				label={ __( 'Enable test mode', 'woocommerce-gateway-stripe' ) }
				help={ interpolateComponents( {
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
				} ) }
			/>
		</>
	);
};

export default TestModeCheckbox;
