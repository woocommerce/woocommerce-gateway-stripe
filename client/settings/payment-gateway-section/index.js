import { __, sprintf } from '@wordpress/i18n';
import { React } from 'react';
import {
	Card,
	CheckboxControl,
	TextControl,
	Button,
} from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';
import styled from '@emotion/styled';
import CardBody from '../card-body';
import { gatewaysInfo } from '../payment-gateway-manager/constants';
import LoadablePaymentGatewaySection from '../loadable-payment-gateway-section';
import PaymentMethodMissingCurrencyPill from '../../components/payment-method-missing-currency-pill';
import useWebhookStateMessage from '../account-details/use-webhook-state-message';
import {
	useEnabledPaymentGateway,
	usePaymentGatewayName,
	usePaymentGatewayDescription,
} from '../../data/payment-gateway/hooks';
import PaymentMethodCapabilityStatusPill from 'wcstripe/components/payment-method-capability-status-pill';
import { WebhookInformation } from 'wcstripe/components/webhook-information';

const StyledCard = styled( Card )`
	margin-bottom: 12px;
`;

const StyledCheckboxLabel = styled.span`
	display: inline-flex;
	gap: 8px;
	align-items: center;
`;

const PaymentGatewaySection = () => {
	const { section } = getQuery();
	const info = gatewaysInfo[ section ];
	const { Fields } = info;
	const [ enableGateway, setEnableGateway ] = useEnabledPaymentGateway();
	const [ gatewayName, setGatewayName ] = usePaymentGatewayName();
	const [
		gatewayDescription,
		setGatewayDescription,
	] = usePaymentGatewayDescription();
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
					{ Fields && <Fields /> }
					<h4>
						{ __(
							'Webhook endpoints',
							'woocommerce-gateway-stripe'
						) }
					</h4>
					<WebhookInformation />
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
