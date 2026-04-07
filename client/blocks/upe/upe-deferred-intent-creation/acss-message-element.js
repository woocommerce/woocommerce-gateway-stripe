import { __ } from '@wordpress/i18n';
import { getStripeImageUrl } from 'wcstripe/blocks/utils';

const AcssMessageElement = () => {
	return (
		<div
			style={ {
				display: 'flex',
				alignItems: 'center',
				gap: '12px',
				margin: 0,
				padding: '29.66625px 9.88875px 14.833125px',
			} }
		>
			<img
				src={ getStripeImageUrl( 'acss-redirect' ) }
				role="presentation"
				alt="ACSS submission icon"
				style={ {
					flexShrink: 0,
					width: '3em',
					height: '3em',
				} }
			/>
			<span
				style={ {
					margin: 0,
					padding: 0,
					fontSize: '16px',
					lineHeight: 1.6,
				} }
			>
				{ __(
					'After submission, you will need to authorize the payment with your bank.',
					'woocommerce-gateway-stripe'
				) }
			</span>
		</div>
	);
};

export default AcssMessageElement;
