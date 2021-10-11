import { __, sprintf } from '@wordpress/i18n';
import { React, useState } from 'react';
import { Card, CheckboxControl, TextControl } from '@wordpress/components';
import styled from '@emotion/styled';
import interpolateComponents from 'interpolate-components';
import CardBody from '../card-body';

const StyledCard = styled( Card )`
	margin-bottom: 12px;
`;

const PaymentGatewaySection = () => {
	const [ enableGateway, setEnableGateway ] = useState( false );
	const [ gatewayName, setGatewayName ] = useState( 'Gateway' );
	const [ gatewayDescription, setGatewayDescription ] = useState(
		'You will be redirected to Gateway.'
	);
	return (
		<StyledCard>
			<CardBody>
				<CheckboxControl
					checked={ enableGateway }
					onChange={ setEnableGateway }
					label={ __(
						'Enable Gateway',
						'woocommerce-gateway-stripe'
					) }
					help={ __(
						'When enabled, Gateway will appear on checkout.',
						'woocommerce-gateway-stripe'
					) }
				/>
				<h4>
					{ __( 'Display settings', 'woocommerce-gateway-stripe' ) }
				</h4>
				<TextControl
					help={ __(
						'Enter a name which customers will see during checkout.',
						'woocommerce-gateway-stripe'
					) }
					label={ __( 'Name', 'woocommerce-gateway-stripe' ) }
					value={ gatewayName }
					onChange={ setGatewayName }
					maxLength={ 22 }
				/>
				<TextControl
					help={ __(
						'Describe how customers should use this payment method during checkout.',
						'woocommerce-gateway-stripe'
					) }
					label={ __( 'Description', 'woocommerce-gateway-stripe' ) }
					value={ gatewayDescription }
					onChange={ setGatewayDescription }
					maxLength={ 22 }
				/>
				<h4>
					{ __( 'Webhook endpoints', 'woocommerce-gateway-stripe' ) }
				</h4>
				<p>
					{ interpolateComponents( {
						mixedString: __(
							"You must add the following webhook endpoint {{webhookLink /}} to your {{stripeSettingsLink /}} (if there isn't one already enabled). This will enable you to receive notifications on the charge statuses.",
							'woocommerce-gateway-stripe'
						),
						components: {
							webhookLink: <a href="?TODO">XXX</a>,
							stripeSettingsLink: (
								<a href="?TODO" target="_blank">
									{ __(
										'Stripe account settings',
										'woocommerce-gateway-stripe'
									) }
								</a>
							),
						},
					} ) }
				</p>
				<p>
					{ sprintf(
						/* translators: %s: date */
						__(
							'No live webhooks have been received since monitoring began at %s.'
						),
						new Date()
							.toISOString()
							.replace( 'T', ' ' )
							.replace( /:\d{2}\..*/, ' UTC' )
					) }
				</p>
			</CardBody>
		</StyledCard>
	);
};

export default PaymentGatewaySection;
