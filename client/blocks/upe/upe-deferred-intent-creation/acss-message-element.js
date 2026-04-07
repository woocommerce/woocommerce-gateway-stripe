import { __ } from '@wordpress/i18n';
import { getStripeImageUrl } from 'wcstripe/blocks/utils';

const AcssMessageElement = () => {
	return (
		<div className="wc-stripe-acss-notice">
			<img
				className="wc-stripe-acss-notice__icon"
				src={ getStripeImageUrl( 'acss-redirect' ) }
				role="presentation"
				alt=""
			/>
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
