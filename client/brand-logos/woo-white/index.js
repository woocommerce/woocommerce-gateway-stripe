import { __ } from '@wordpress/i18n';
import logo from './logo.svg';

const WooLogo = ( props ) => (
	<img
		className="woocommerce-stripe-woo-white-logo"
		src={ logo }
		width="64"
		height="64"
		alt={ __( 'Woo logo', 'woocommerce-gateway-stripe' ) }
		{ ...props }
	/>
);

export default WooLogo;
