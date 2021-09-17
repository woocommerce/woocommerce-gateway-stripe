import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import React from 'react';
import SectionStatus from '../../components/section-status';
import './style.scss';

const PaymentsSection = ( props ) => {
	return (
		<div className="account-details__row">
			<p>{ __( 'Payments:', 'woocommerce-gateway-stripe' ) }</p>
			<SectionStatus { ...props } />
		</div>
	);
};

const DepositsSection = ( props ) => {
	return (
		<div className="account-details__row">
			<p>{ __( 'Deposits:', 'woocommerce-gateway-stripe' ) }</p>
			<SectionStatus { ...props } />
		</div>
	);
};

const MissingAccountDetailsDescription = ( { accountStatus } ) => {
	const { accountLink, paymentsEnabled, depositsEnabled } = accountStatus;

	let description = '';
	if ( ! paymentsEnabled || ! depositsEnabled ) {
		description = createInterpolateElement(
			/* translators: <a> - dashboard login URL */
			__(
				'Payments/deposits may be disabled for this account until missing business information is updated. <a>Update now</a>',
				'woocommerce-gateway-stripe'
			),
			// eslint-disable-next-line jsx-a11y/anchor-has-content
			{ a: <a href={ accountLink } /> }
		);
	}

	if ( ! description ) {
		return null;
	}

	return <div className="account-details__desc">{ description }</div>;
};

const AccountDetails = ( { accountStatus } ) => {
	if ( accountStatus.error ) {
		return (
			<div>
				<p className="account-details__error">
					{ __(
						'Error determining the connection status.',
						'woocommerce-gateway-stripe'
					) }
				</p>
			</div>
		);
	}

	return (
		<div>
			<div className="account-details__flex-container">
				<PaymentsSection isEnabled={ accountStatus.paymentsEnabled } />
				<DepositsSection isEnabled={ accountStatus.depositsEnabled } />
			</div>
			<MissingAccountDetailsDescription accountStatus={ accountStatus } />
		</div>
	);
};

export default AccountDetails;
