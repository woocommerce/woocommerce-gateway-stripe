/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';

const PaymentsStatusEnabled = () => {
	return (
		<span className="account-details__info--green">
			<Icon icon="yes-alt" />
			{ __( 'Enabled', 'woocommerce-gateway-stripe' ) }
		</span>
	);
};

const PaymentsStatusDisabled = () => {
	return (
		<span className="account-details__info--yellow">
			<Icon icon="warning" />
			{ __( 'Disabled', 'woocommerce-gateway-stripe' ) }
		</span>
	);
};

const PaymentsStatus = ( { isEnabled } ) => {
	return isEnabled ? <PaymentsStatusEnabled /> : <PaymentsStatusDisabled />;
};

export default PaymentsStatus;
