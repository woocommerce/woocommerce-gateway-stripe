import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import { React } from 'react';
import {
	Card,
	CheckboxControl,
	TextControl,
	ExternalLink,
	Button,
} from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';
import styled from '@emotion/styled';
import CardBody from '../card-body';
import { gatewaysInfo } from '../payment-gateway-manager/constants';
import LoadablePaymentGatewaySection from '../loadable-payment-gateway-section';
import PaymentMethodMissingCurrencyPill from '../../components/payment-method-missing-currency-pill';
import { useAccount } from '../../data/account/hooks';
import useWebhookStateMessage from '../account-details/use-webhook-state-message';
import {
	useEnabledPaymentGateway,
	usePaymentGatewayName,
	usePaymentGatewayDescription,
} from '../../data/payment-gateway/hooks';
import PaymentMethodCapabilityStatusPill from 'wcstripe/components/payment-method-capability-status-pill';

const StyledCard = styled( Card )`
	margin-bottom: 12px;
`;

const WebhookEndpointText = styled.strong`
	padding: 0 2px;
	background-color: #f6f7f7; // $studio-gray-0
`;

const StyledCheckboxLabel = styled.span`
	display: inline-flex;
	gap: 8px;
	align-items: center;
`;

const PaymentGatewaySection = () => {
	const { section } = getQuery();
	const info = gatewaysInfo[ section ];
	const [ enableGateway, setEnableGateway ] = useEnabledPaymentGateway();
	const [ gatewayName, setGatewayName ] = usePaymentGatewayName();
	const [
		gatewayDescription,
		setGatewayDescription,
	] = usePaymentGatewayDescription();
	const { data } = useAccount();
	const { message, requestStatus, refreshMessage } = useWebhookStateMessage();

	return (
		<StyledCard>
			<LoadablePaymentGatewaySection numLines={ 34 }>
				<CardBody>
					<CheckboxControl
						checked={ enableGateway }
						onChange={ setEnableGateway }
						label={
							<StyledCheckboxLabel>
								{ sprintf(
									/* translators: %s: Payment Gateway name */
									__(
										'Enable %s',
										'woocommerce-gateway-stripe'
									),
									info.title
								) }

								<PaymentMethodCapabilityStatusPill
									id={ info.id }
									label={ info.title }
								/>
								<PaymentMethodMissingCurrencyPill
									id={ info.id }
									label={ info.title }
								/>
							</StyledCheckboxLabel>
						}
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
						{ __(
							'Display settings',
							'woocommerce-gateway-stripe'
						) }
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
						label={ __(
							'Description',
							'woocommerce-gateway-stripe'
						) }
						value={ gatewayDescription }
						onChange={ setGatewayDescription }
					/>
					<h4>
						{ __(
							'Webhook endpoints',
							'woocommerce-gateway-stripe'
						) }
					</h4>
					<p>
						{ createInterpolateElement(
							__(
								"You must add the following webhook endpoint <webhookEndpoint /> to your <a>Stripe account settings</a> (if there isn't one already enabled). This will enable you to receive notifications on the charge statuses.",
								'woocommerce-gateway-stripe'
							),
							{
								webhookEndpoint: (
									<WebhookEndpointText>
										{ data.webhook_url }
									</WebhookEndpointText>
								),
								a: (
									<ExternalLink href="https://dashboard.stripe.com/account/webhooks" />
								),
							}
						) }
					</p>
					<p>
						{ message }{ ' ' }
						<Button
							disabled={ requestStatus === 'pending' }
							onClick={ refreshMessage }
							isBusy={ requestStatus === 'pending' }
							isLink
						>
							{ __( 'Refresh', 'woocommerce-gateway-stripe' ) }
						</Button>
					</p>
				</CardBody>
			</LoadablePaymentGatewaySection>
		</StyledCard>
	);
};

export default PaymentGatewaySection;
