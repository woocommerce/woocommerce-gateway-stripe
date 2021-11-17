import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import React from 'react';
import styled from '@emotion/styled';
import './style.scss';
import { Button, ExternalLink } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import useWebhookStateMessage from './use-webhook-state-message';
import SectionStatus from './section-status';
import { useAccount, useGetCapabilities } from 'wcstripe/data/account';
import {
	useAccountKeysTestWebhookSecret,
	useAccountKeysWebhookSecret,
} from 'wcstripe/data/account-keys';

const WebhookEndpointText = styled.strong`
	padding: 0 2px;
	background-color: #f6f7f7; // $studio-gray-0
`;

const useIsCardPaymentsEnabled = () =>
	useGetCapabilities().card_payments === 'active';

const useAreDepositsEnabled = () => {
	const { data } = useAccount();

	return (
		data.account?.payouts_enabled &&
		Boolean( data.account?.settings?.payouts?.schedule?.interval )
	);
};

const PaymentsSection = () => {
	const isEnabled = useIsCardPaymentsEnabled();

	return (
		<div className="account-details__row">
			<p>{ __( 'Payments:', 'woocommerce-gateway-stripe' ) }</p>
			<SectionStatus isEnabled={ isEnabled }>
				{ isEnabled
					? __( 'Enabled', 'woocommerce-gateway-stripe' )
					: __( 'Disabled', 'woocommerce-gateway-stripe' ) }
			</SectionStatus>
		</div>
	);
};

const DepositsSection = () => {
	const isEnabled = useAreDepositsEnabled();

	return (
		<div className="account-details__row">
			<p>{ __( 'Deposits:', 'woocommerce-gateway-stripe' ) }</p>
			<SectionStatus isEnabled={ isEnabled }>
				{ isEnabled
					? __( 'Enabled', 'woocommerce-gateway-stripe' )
					: __( 'Disabled', 'woocommerce-gateway-stripe' ) }
			</SectionStatus>
		</div>
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

	return (
		<>
			<div className="account-details__row">
				<p>{ __( 'Webhooks:', 'woocommerce-gateway-stripe' ) }</p>
				<SectionStatus isEnabled={ isWebhookSecretEntered }>
					{ isWebhookSecretEntered
						? __( 'Enabled', 'woocommerce-gateway-stripe' )
						: __(
								'Please enter the webhook secret key for this to work properly',
								'woocommerce-gateway-stripe'
						  ) }
				</SectionStatus>
			</div>
			<div className="account-details__desc">
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
				<br />
				<br />
				{ message }{ ' ' }
				<Button
					disabled={ requestStatus === 'pending' }
					onClick={ refreshMessage }
					isBusy={ requestStatus === 'pending' }
					isLink
				>
					{ __( 'Refresh', 'woocommerce-gateway-stripe' ) }
				</Button>
			</div>
		</>
	);
};

const MissingAccountDetailsDescription = () => {
	const isPaymentsEnabled = useIsCardPaymentsEnabled();
	const areDepositsEnabled = useAreDepositsEnabled();

	if ( isPaymentsEnabled && areDepositsEnabled ) {
		return null;
	}

	return (
		<div className="account-details__desc">
			{ createInterpolateElement(
				/* translators: <a> - dashboard login URL */
				__(
					'Payments/deposits may be disabled for this account until missing business information is updated. <a>Update now</a>',
					'woocommerce-gateway-stripe'
				),
				{ a: <ExternalLink href="https://stripe.com/support" /> }
			) }
		</div>
	);
};

const AccountDetails = () => {
	const { data } = useAccount();
	const isTestModeEnabled = Boolean( data.testmode );

	const hasAccountError = Object.keys( data.account ?? {} ).length === 0;
	if ( hasAccountError ) {
		return (
			<div>
				<p className="account-details__error">
					{ isTestModeEnabled
						? interpolateComponents( {
								mixedString: __(
									"Seems like the test keys we've saved for you are no longer valid. If you recently updated them, enter the new test keys from your {{accountLink}}Stripe Account{{/accountLink}}.",
									'woocommerce-gateway-stripe'
								),
								components: {
									accountLink: (
										// eslint-disable-next-line jsx-a11y/anchor-has-content
										<a href="https://dashboard.stripe.com/test/apikeys" />
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
										// eslint-disable-next-line jsx-a11y/anchor-has-content
										<a href="https://dashboard.stripe.com/apikeys" />
									),
								},
						  } ) }
				</p>
			</div>
		);
	}

	return (
		<div>
			<div className="account-details__flex-container">
				<PaymentsSection />
				<DepositsSection />
				<MissingAccountDetailsDescription />
				<WebhooksSection />
			</div>
		</div>
	);
};

export default AccountDetails;
