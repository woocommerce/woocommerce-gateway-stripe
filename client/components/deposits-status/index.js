/**
 * External dependencies
 */
import React from 'react';
import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */

const DepositsStatus = ( props ) => {
	const { isEnabled } = props;
	let className = 'account-details__info--green';
	let description;
	let icon = <Icon icon="yes-alt" />;

	if ( isEnabled === true ) {
		description = __( 'Enabled', 'woocommerce-gateway-stripe' );
	} else {
		className = 'account-details__info--yellow';
		icon = <Icon icon="warning" />;
		description = __( 'Disabled', 'woocommerce-gateway-stripe' );
	}

	return (
		<span className={ className }>
			{ icon }
			{ description }
		</span>
	);
};

export default DepositsStatus;
