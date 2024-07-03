import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import React from 'react';
import { Button, ExternalLink, Icon } from '@wordpress/components';
import { help, warning } from '@wordpress/icons';
import interpolateComponents from 'interpolate-components';
import styled from '@emotion/styled';
import SectionStatus from '../section-status';
import useWebhookStateMessage from './use-webhook-state-message';
import Tooltip from 'wcstripe/components/tooltip';
import { useAccount } from 'wcstripe/data/account';
import {
	useAccountKeysTestWebhookSecret,
	useAccountKeysWebhookSecret,
} from 'wcstripe/data/account-keys';
import { WebhookInformation } from 'wcstripe/components/webhook-information';

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

const WebhookDescriptionWrapper = styled.div`
	font-size: 12px;
	font-style: normal;
	color: rgb( 117, 117, 117 );

	> span {
		align-self: center;
	}

	p.warning {
		background-color: #fcf9e8;
		color: #674600;
		padding: 4px 8px;
		border-radius: 2px;
	}
`;

const WebhookDescription = styled.div`
	display: flex;
	align-items: center;
`;

const WarningIcon = styled( Icon )`
	fill: #674600;
	padding: 5px;
	margin: 1em 0;
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
			</SectionStatus>
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
		</AccountSection>
	);
};

const WebhooksSection = () => {
	const [ testWebhookSecret ] = useAccountKeysTestWebhookSecret();
	const [ webhookSecret ] = useAccountKeysWebhookSecret();
	const { data } = useAccount();
	const isTestModeEnabled = Boolean( data.testmode );

	const isWebhookSecretEntered = Boolean(
		isTestModeEnabled ? testWebhookSecret : webhookSecret
	);

	const { message, requestStatus, refreshMessage } = useWebhookStateMessage();
	const isWarningMessage = message?.includes( 'Warning: ' ) || false;

	return (
		<>
			<AccountSection>
				<Label>{ __( 'Webhook', 'woocommerce-gateway-stripe' ) }</Label>
				<SectionStatus isEnabled={ isWebhookSecretEntered }>
					{ isWebhookSecretEntered
						? __( 'Enabled', 'woocommerce-gateway-stripe' )
						: __( 'Disabled', 'woocommerce-gateway-stripe' ) }
				</SectionStatus>
			</AccountSection>
			<WebhookDescriptionWrapper>
				{ ! isWebhookSecretEntered && <WebhookInformation /> }
				<WebhookDescription
					className={ isWebhookSecretEntered ? 'expanded' : '' }
				>
					{ isWarningMessage && (
						<span data-testid="warning">
							<WarningIcon icon={ warning } size="16" />
						</span>
					) }
					<p className={ isWarningMessage ? 'warning' : '' }>
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
				</WebhookDescription>
			</WebhookDescriptionWrapper>
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
					{ isTestModeEnabled
						? interpolateComponents( {
								mixedString: __(
									"Seems like the test keys we've saved for you are no longer valid. If you recently updated them, enter the new test keys from your {{accountLink}}Stripe Account{{/accountLink}}.",
									'woocommerce-gateway-stripe'
								),
								components: {
									accountLink: (
										<ExternalLink href="https://dashboard.stripe.com/test/apikeys" />
									),
								},
						  } )
						: interpolateComponents( {
								mixedString: __(
									"Seems like the live keys we've saved for you are no longer valid. If you recently updated them, enter the new live keys from your {{accountLink}}Stripe Account{{/accountLink}}.",
									'woocommerce-gateway-stripe'
								),
								components: {
									accountLink: (
										<ExternalLink href="https://dashboard.stripe.com/apikeys" />
									),
								},
						  } ) }
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
