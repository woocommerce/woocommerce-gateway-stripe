import { __ } from '@wordpress/i18n';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';

export const CustomButton = ( { onButtonClicked } ) => {
	const {
		theme = 'dark',
		height = '44',
		customLabel = __( 'Buy now', 'woocommerce-gateway-stripe' ),
	} = getBlocksConfiguration()?.button;
	return (
		<button
			type="button"
			id="wc-stripe-custom-button"
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
