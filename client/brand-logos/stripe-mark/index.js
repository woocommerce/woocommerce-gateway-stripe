import { __ } from '@wordpress/i18n';
import mark from './mark.svg';

const StripeMark = ( props ) => (
	<img
		className="woocommerce-stripe-mark-logo"
		src={ mark }
		width="64"
		height="64"
		alt={ __( 'Stripe logo', 'woocommerce-gateway-stripe' ) }
		{ ...props }
	/>
);

export default StripeMark;
