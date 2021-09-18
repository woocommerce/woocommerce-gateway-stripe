import { __ } from '@wordpress/i18n';
import { React } from 'react';
import { Button, Card, CheckboxControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import styled from '@emotion/styled';
import CardBody from '../card-body';
import CardFooter from '../card-footer';
import { useIsStripeEnabled, useTestMode } from 'wcstripe/data';

const StyledCard = styled( Card )`
	margin-bottom: 12px;
`;

const GeneralSettingsSection = () => {
	const [ isStripeEnabled, setIsStripeEnabled ] = useIsStripeEnabled();
	const [ isTestModeEnabled, setIsTestModeEnabled ] = useTestMode();

	return (
		<StyledCard>
			<CardBody>
				<CheckboxControl
					checked={ isStripeEnabled }
					onChange={ setIsStripeEnabled }
					label={ __(
						'Enable Stripe',
						'woocommerce-gateway-stripe'
					) }
					help={ __(
						'When enabled, payment methods powered by Stripe will appear on checkout.',
						'woocommerce-gateway-stripe'
					) }
				/>

				<CheckboxControl
					checked={ isTestModeEnabled }
					onChange={ setIsTestModeEnabled }
					label={ __(
						'Enable test mode',
						'woocommerce-gateway-stripe'
					) }
					help={ interpolateComponents( {
						mixedString: __(
							'Use {{testCardNumbersLink /}} to simulate various transactions. {{learnMoreLink/}}',
							'woocommerce-gateway-stripe'
						),
						components: {
							testCardNumbersLink: (
								<a href="https://stripe.com/docs/testing#cards">
									test card numbers
								</a>
							),
							learnMoreLink: (
								<a href="https://stripe.com/docs/testing">
									Learn more
								</a>
							),
						},
					} ) }
				/>
			</CardBody>
			<CardFooter>
				<Button isSecondary href="?TODO">
					{ __( 'Edit account keys', 'woocommerce-gateway-stripe' ) }
				</Button>
			</CardFooter>
		</StyledCard>
	);
};

export default GeneralSettingsSection;
