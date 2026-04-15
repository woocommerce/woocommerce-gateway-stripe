import { __ } from '@wordpress/i18n';
import { getStripeImageUrl } from 'wcstripe/blocks/utils';

const AcssMessageElement = () => {
	return (
		<div className="wc-stripe-acss-notice">
			<svg
				className="wc-stripe-acss-notice__icon"
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 48 40"
				fill="currentColor"
				role="presentation"
			>
				<use
					href={ `${ getStripeImageUrl( 'acss-redirect' ) }#icon` }
				/>
			</svg>
			<span className="wc-stripe-acss-notice__text">
				{ __(
					'After submission, you will need to authorize the payment with your bank.',
					'woocommerce-gateway-stripe'
				) }
			</span>
		</div>
	);
};

export default AcssMessageElement;
