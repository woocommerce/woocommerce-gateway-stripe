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
import styled from '@emotion/styled';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';

const GeneralSettingsSection = () => {
	const [ enableStripe, setEnableStripe ] = useState( false );
	const [ enableTestMode, setEnableTestMode ] = useState( false );

	const toggleEnableStripe = () => setEnableStripe( ! enableStripe );
	const toggleEnableTestMode = () => setEnableTestMode( ! enableTestMode );

	const CheckboxDescription = styled.span`
		font-style: italic;
		display: block;
	`;

	const GeneralSettingsOptionWrapper = styled.div`
		&:first-of-type {
			margin-top: 26px;
		}
		&:not( :last-child ) {
			margin-bottom: 26px;
		}
		&:last-child {
			margin-bottom: 24px;
		}
	`;

	return (
		<Card>
			<CardBody>
				<GeneralSettingsOptionWrapper>
					<CheckboxControl
						checked={ enableStripe }
						onChange={ toggleEnableStripe }
						label={ __(
							'Enable Stripe',
							'woocommerce-gateway-stripe'
						) }
					/>
					<CheckboxDescription>
						{ __(
							'When enabled, payment methods powered by Stripe will appear on checkout.',
							'woocommerce-gateway-stripe'
						) }
					</CheckboxDescription>
				</GeneralSettingsOptionWrapper>

				<GeneralSettingsOptionWrapper>
					<CheckboxControl
						checked={ enableTestMode }
						onChange={ toggleEnableTestMode }
						label={ __(
							'Enable test mode',
							'woocommerce-gateway-stripe'
						) }
					/>
					<CheckboxDescription>
						{ interpolateComponents( {
							mixedString: __(
								'Use {{testCardNumbersLink /}} to simulate various transactions. {{learnMoreLink/}}',
								'woocommerce-gateway-stripe'
							),
							components: {
								testCardNumbersLink: (
									<a href="?TODO">test card numbers</a>
								),
								learnMoreLink: <a href="?TODO">Learn more</a>,
							},
						} ) }
					</CheckboxDescription>
				</GeneralSettingsOptionWrapper>
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
