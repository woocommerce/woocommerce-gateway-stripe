/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';

/**
 * Internal dependencies
 */
import '../account-status/shared.scss';

const PaymentsStatusEnabled = ( props ) => {
	const { iconSize } = props;

	return (
		<span className={ 'account-status__info__green' }>
			<Icon icon="dashicons-yes-alt" size={ iconSize } />
			{ __( 'Enabled', 'woocommerce-gateway-stripe' ) }
		</span>
	);
};

const PaymentsStatusDisabled = ( props ) => {
	const { iconSize } = props;

	return (
		<span className={ 'account-status__info__red' }>
			<Icon icon="dashicons-warning" size={ iconSize } />
			{ __( 'Disabled', 'woocommerce-gateway-stripe' ) }
		</span>
	);
};

const PaymentsStatus = ( props ) => {
	const { paymentsEnabled } = props;

	return paymentsEnabled ? (
		<PaymentsStatusEnabled { ...props } />
	) : (
		<PaymentsStatusDisabled { ...props } />
	);
};

export default PaymentsStatus;
