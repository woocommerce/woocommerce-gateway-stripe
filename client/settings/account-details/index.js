/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import DepositsEnabled from '../../components/deposits-status';
import PaymentsStatus from '../../components/payments-status';
import './style.scss';

const renderPaymentsStatus = ( paymentsEnabled ) => {
	return (
		<div className="account-details__row">
			<p>{ __( 'Payments:', 'woocommerce-gateway-stripe' ) }</p>
			<PaymentsStatus
				iconSize={ 18 }
				paymentsEnabled={ paymentsEnabled }
			/>
		</div>
	);
};

const renderdepositsEnabled = ( depositsEnabled ) => {
	return (
		<div className="account-details__row">
			<p>{ __( 'Deposits:', 'woocommerce-gateway-stripe' ) }</p>
			<DepositsEnabled
				iconSize={ 18 }
				depositsEnabled={ depositsEnabled }
			/>
		</div>
	);
};

const renderMissingAccountDetailsDescription = ( accountStatus ) => {
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

const renderBaseFees = ( baseFees ) => {
	return (
		<div className="account-details__row">
			<p>{ __( 'Base Fees:', 'woocommerce-gateway-stripe' ) }</p>
			<span>{ baseFees }</span>
		</div>
	);
};

const AccountDetails = ( props ) => {
	const { accountStatus } = props;
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
				{ renderPaymentsStatus( accountStatus.paymentsEnabled ) }
				{ renderdepositsEnabled( accountStatus.depositsEnabled ) }
				{ renderBaseFees( accountStatus.baseFees ) }
			</div>
			{ renderMissingAccountDetailsDescription( accountStatus ) }
		</div>
	);
};

export default AccountDetails;
