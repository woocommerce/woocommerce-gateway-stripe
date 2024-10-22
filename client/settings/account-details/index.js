import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import React from 'react';
import { ExternalLink, Icon } from '@wordpress/components';
import { help } from '@wordpress/icons';
import styled from '@emotion/styled';
import SectionStatus from '../section-status';
import Tooltip from 'wcstripe/components/tooltip';
import { useAccount } from 'wcstripe/data/account';
import { WebhookDescription } from 'wcstripe/components/webhook-description';

const AccountDetailsContainer = styled.div`
	display: flex;
	align-self: stretch;
	flex-wrap: wrap;
`;

const AccountSection = styled.div`
	display: flex;
	flex-direction: row;
	align-items: center;
	padding: 8px 0;
	flex: 1 0 0;
	gap: 8px;

	svg {
		display: flex;
		fill: #757575;
	}
`;

const Label = styled.p`
	font-size: 11px;
	font-weight: 500;
	text-transform: uppercase;
	margin: 0;
`;

const AccountDetailsError = styled.p`
	@import '../../styles/abstracts/colors';
	color: $alert-red;
`;

const useIsCardPaymentsEnabled = () => {
	const { data } = useAccount();

	return data.account?.charges_enabled;
};

const useArePayoutsEnabled = () => {
	const { data } = useAccount();

	return data.account?.payouts_enabled;
};

const PaymentsSection = () => {
	const isEnabled = useIsCardPaymentsEnabled();

	return (
		<AccountSection>
			<Label>{ __( 'Payment', 'woocommerce-gateway-stripe' ) }</Label>
			<SectionStatus isEnabled={ isEnabled }>
				{ isEnabled
					? __( 'Enabled', 'woocommerce-gateway-stripe' )
					: __( 'Disabled', 'woocommerce-gateway-stripe' ) }
			</SectionStatus>
		</AccountSection>
	);
};

const PayoutsSection = () => {
	const isEnabled = useArePayoutsEnabled();

	return (
		<AccountSection>
			<Label>{ __( 'Payout', 'woocommerce-gateway-stripe' ) }</Label>
			<SectionStatus isEnabled={ isEnabled }>
				{ isEnabled
					? __( 'Enabled', 'woocommerce-gateway-stripe' )
					: __( 'Disabled', 'woocommerce-gateway-stripe' ) }
				{ ! isEnabled && (
					<Tooltip
						content={ createInterpolateElement(
							/* translators: <a> - dashboard login URL */
							__(
								'Payments/payouts may be disabled for this account until missing business information is updated. <a>Update now</a>',
								'woocommerce-gateway-stripe'
							),
							{
								a: (
									<ExternalLink href="https://dashboard.stripe.com/account" />
								),
							}
						) }
					>
						<span data-testid="help">
							<Icon icon={ help } size="18" />
						</span>
					</Tooltip>
				) }
			</SectionStatus>
		</AccountSection>
	);
};

const WebhooksSection = () => {
	const { data } = useAccount();
	const isWebhookEnabled = Boolean( data.is_webhook_enabled );

	return (
		<>
			<AccountSection>
				<Label>{ __( 'Webhook', 'woocommerce-gateway-stripe' ) }</Label>
				<SectionStatus isEnabled={ isWebhookEnabled }>
					{ isWebhookEnabled
						? __( 'Enabled', 'woocommerce-gateway-stripe' )
						: __( 'Disabled', 'woocommerce-gateway-stripe' ) }
				</SectionStatus>
			</AccountSection>
			<WebhookDescription isWebhookEnabled={ isWebhookEnabled } />
		</>
	);
};

const AccountDetails = () => {
	const { data } = useAccount();
	const isTestModeEnabled = Boolean( data.testmode );

	const hasAccountError = Object.keys( data.account ?? {} ).length === 0;
	if ( hasAccountError ) {
		return (
			<AccountDetailsContainer>
				<AccountDetailsError>
					{ createInterpolateElement(
						isTestModeEnabled
							? __(
									"Seems like the test API keys we've saved for you are no longer valid. If you recently updated them, use the <strong>Configure Connection</strong> button below to reconnect.",
									'woocommerce-gateway-stripe'
							  )
							: __(
									"Seems like the live API keys we've saved for you are no longer valid. If you recently updated them, use the <strong>Configure Connection</strong> button below to reconnect.",
									'woocommerce-gateway-stripe'
							  ),
						{
							strong: <strong />,
						}
					) }
				</AccountDetailsError>
			</AccountDetailsContainer>
		);
	}

	return (
		<AccountDetailsContainer>
			<PaymentsSection />
			<PayoutsSection />
			<WebhooksSection />
		</AccountDetailsContainer>
	);
};

export default AccountDetails;
