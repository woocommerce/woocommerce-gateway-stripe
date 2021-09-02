/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Chip from '../chip';
import './style.scss';

const StatusChip = ( props ) => {
	const { accountStatus } = props;

	let description = __( 'Unknown', 'woocommerce-gateway-stripe' );
	let type = 'light';
	if ( accountStatus === 'complete' ) {
		description = __( 'Complete', 'woocommerce-gateway-stripe' );
		type = 'primary';
	} else if ( accountStatus === 'restricted_soon' ) {
		description = __( 'Restricted soon', 'woocommerce-gateway-stripe' );
		type = 'warning';
	} else if ( accountStatus === 'restricted' ) {
		description = __( 'Restricted', 'woocommerce-gateway-stripe' );
		type = 'alert';
	} else if ( accountStatus.startsWith( 'rejected' ) ) {
		description = __( 'Rejected', 'woocommerce-gateway-stripe' );
		type = 'light';
	}

	return <Chip message={ description } type={ type } isCompact />;
};

export default StatusChip;
