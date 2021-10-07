import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import React from 'react';
import './style.scss';
import { Button } from '@wordpress/components';
import useWebhookStateMessage from './use-webhook-state-message';
import SectionStatus from './section-status';
import { useAccount, useGetCapabilities } from 'wcstripe/data/account';
import {
	useAccountKeysTestWebhookSecret,
	useAccountKeysWebhookSecret,
} from 'wcstripe/data/account-keys';
import { useTestMode } from 'wcstripe/data';

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
	const [ isTestModeEnabled ] = useTestMode();

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
				{ message }{ ' ' }
				<Button
					disabled={ requestStatus === 'pending' }
					onClick={ refreshMessage }
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
				// eslint-disable-next-line jsx-a11y/anchor-has-content
				{ a: <a href="https://stripe.com/support" /> }
			) }
		</div>
	);
};

const AccountDetails = () => {
	const { data } = useAccount();

	const hasAccountError = Object.keys( data.account ?? {} ).length === 0;
	if ( hasAccountError ) {
		return (
			<div>
				<p className="account-details__error">
					{ __(
						'Error determining the account connection status.',
						'woocommerce-gateway-stripe'
					) }
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
