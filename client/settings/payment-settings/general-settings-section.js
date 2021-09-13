/**
 * External dependencies
 */
import { React, useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardFooter,
	CheckboxControl,
} from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';

const GeneralSettingsSection = () => {
	const [ enableStripe, setEnableStripe ] = useState( false );
	const [ enableTestMode, setEnableTestMode ] = useState( false );

	return (
		<Card>
			<CardBody>
				<CheckboxControl
					checked={ enableStripe }
					onChange={ setEnableStripe }
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
					checked={ enableTestMode }
					onChange={ setEnableTestMode }
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
		</Card>
	);
};

export default GeneralSettingsSection;
