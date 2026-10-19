import { React } from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import InlineNotice from 'wcstripe/components/inline-notice';

/**
 * ConnectionErrorNotice component.
 *
 * Displays an error notice when there's an issue connecting to Stripe.
 *
 * @param {Object} props           The component props.
 * @param {string} [props.message] Optional plain-text message override.
 *
 * @return {JSX.Element} The rendered ConnectionErrorNotice component.
 */
const ConnectionErrorNotice = ( { message } = {} ) => {
	const errorMessage =
		message ||
		__(
			'An issue occurred generating a connection to Stripe. Please try again.',
			'woocommerce-gateway-stripe'
		);

	return (
		<InlineNotice isDismissible={ false } status="error">
			{ errorMessage }
			<br />
			{ interpolateComponents( {
				mixedString: __(
					'For assistance, refer to our {{Link}}documentation{{/Link}}.',
					'woocommerce-gateway-stripe'
				),
				components: {
					Link: (
						<ExternalLink href="https://woocommerce.com/document/stripe/setup-and-configuration/connecting-to-stripe/" />
					),
				},
			} ) }
		</InlineNotice>
	);
};

export default ConnectionErrorNotice;
