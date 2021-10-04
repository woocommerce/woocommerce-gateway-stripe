import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import React from 'react';
import SectionStatus from '../../components/section-status';
import './style.scss';
import { useAccount, useGetCapabilities } from 'wcstripe/data/account';

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
			<SectionStatus isEnabled={ isEnabled } />
		</div>
	);
};

const DepositsSection = () => {
	const isEnabled = useAreDepositsEnabled();

	return (
		<div className="account-details__row">
			<p>{ __( 'Deposits:', 'woocommerce-gateway-stripe' ) }</p>
			<SectionStatus isEnabled={ isEnabled } />
		</div>
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
			</div>
			<MissingAccountDetailsDescription />
		</div>
	);
};

export default AccountDetails;
