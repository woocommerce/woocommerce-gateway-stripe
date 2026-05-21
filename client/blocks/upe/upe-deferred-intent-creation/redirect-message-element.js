import { getStripeImageUrl } from 'wcstripe/blocks/utils';

const RedirectMessageElement = ( { text } ) => {
	return (
		<fieldset className="wc-stripe-redirect-notice">
			<svg
				className="wc-stripe-redirect-notice__icon"
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 48 40"
				fill="currentColor"
				role="presentation"
			>
				<use
					href={ `${ getStripeImageUrl( 'payment-redirect' ) }#icon` }
				/>
			</svg>
			<span className="wc-stripe-redirect-notice__text">{ text }</span>
		</fieldset>
	);
};

export default RedirectMessageElement;
