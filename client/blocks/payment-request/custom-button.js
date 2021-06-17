/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { getStripeServerData } from '../stripe-utils';

export const CustomButton = ( { onButtonClicked } ) => {
	const {
		theme = 'dark',
		height = '44',
		customLabel = __( 'Buy now', 'woocommerce-gateway-stripe' ),
	} = getStripeServerData()?.button;
	return (
		<button
			type={ 'button' }
			id={ 'wc-stripe-custom-button' }
			className={ `button ${ theme } is-active` }
			style={ {
				height: height + 'px',
			} }
			onClick={ onButtonClicked }
		>
			{ customLabel }
		</button>
	);
};
