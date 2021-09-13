/**
 * External dependencies
 */
import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */

const DepositsEnabled = ( props ) => {
	const { depositsEnabled, iconSize } = props;
	let className = 'account-details__info__green';
	let description;
	let icon = <Icon icon="yes-alt" size={ iconSize } />;

	if ( depositsEnabled === true ) {
		description = __( 'Enabled', 'woocommerce-gateway-stripe' );
	} else {
		className = 'account-details__info__yellow';
		icon = <Icon icon="warning" size={ iconSize } />;
		description = __( 'Disabled', 'woocommerce-gateway-stripe' );
	}

	return (
		<span className={ className }>
			{ icon }
			{ description }
		</span>
	);
};

export default DepositsEnabled;
