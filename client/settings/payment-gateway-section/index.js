import { __, sprintf } from '@wordpress/i18n';
import { React } from 'react';
import { Card, CheckboxControl, TextControl } from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';
import styled from '@emotion/styled';
import interpolateComponents from 'interpolate-components';
import CardBody from '../card-body';
import { gatewaysInfo } from '../payment-gateway-manager/constants';
import {
	useEnabledGateway,
	useGatewayName,
	useGatewayDescription,
} from './hooks';

const StyledCard = styled( Card )`
	margin-bottom: 12px;
`;

const PaymentGatewaySection = () => {
	const { section } = getQuery();
	const info = gatewaysInfo[ section ];
	const [ enableGateway, setEnableGateway ] = useEnabledGateway();
	const [ gatewayName, setGatewayName ] = useGatewayName();
	const [
		gatewayDescription,
		setGatewayDescription,
	] = useGatewayDescription();
	return (
		<StyledCard>
			<CardBody>
				<CheckboxControl
					checked={ enableGateway }
					onChange={ setEnableGateway }
					label={ sprintf(
						/* translators: %s: Payment Gateway name */
						__( 'Enable %s', 'woocommerce-gateway-stripe' ),
						info.title
					) }
					help={ sprintf(
						/* translators: %s: Payment Gateway name */
						__(
							'When enabled, %s will appear on checkout.',
							'woocommerce-gateway-stripe'
						),
						info.title
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
				/>
				<TextControl
					help={ __(
						'Describe how customers should use this payment method during checkout.',
						'woocommerce-gateway-stripe'
					) }
					label={ __( 'Description', 'woocommerce-gateway-stripe' ) }
					value={ gatewayDescription }
					onChange={ setGatewayDescription }
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
							webhookLink: (
								<span className="code">{ `${ location.origin }/?wc-api=wc_stripe` }</span>
							),
							stripeSettingsLink: (
								<a
									href="https://dashboard.stripe.com/account/webhooks"
									target="_blank"
									rel="external noopener noreferrer"
								>
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
