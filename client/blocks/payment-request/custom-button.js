/**
 * Internal dependencies
 */
import { getStripeServerData } from '../stripe-utils';

export const CustomButton = ( { onButtonClicked } ) => {
	const {
		theme = 'dark',
		height = '44',
		customLabel = 'Buy now',
	} = getStripeServerData().button;
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
