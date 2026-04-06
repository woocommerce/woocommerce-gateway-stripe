import { __ } from '@wordpress/i18n';

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
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 48 40"
				fill="rgb(43, 45, 47)"
				role="presentation"
				style={ {
					flexShrink: 0,
					width: '3em',
					height: '3em',
				} }
			>
				<path
					opacity=".6"
					fillRule="evenodd"
					clipRule="evenodd"
					d="M0 8a4 4 0 014-4h30a4 4 0 014 4v8a1 1 0 11-2 0v-4a2 2 0 00-2-2H4a2 2 0 00-2 2v20a2 2 0 002 2h30a2 2 0 002-2v-6a1 1 0 112 0v6a4 4 0 01-4 4H4a4 4 0 01-4-4V8zm4 0a1 1 0 100-2 1 1 0 000 2zm3 0a1 1 0 100-2 1 1 0 000 2zm4-1a1 1 0 11-2 0 1 1 0 012 0zm29.992 9.409L44.583 20H29a1 1 0 100 2h15.583l-3.591 3.591a1 1 0 101.415 1.416l5.3-5.3a1 1 0 000-1.414l-5.3-5.3a1 1 0 10-1.415 1.416z"
				/>
			</svg>
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
