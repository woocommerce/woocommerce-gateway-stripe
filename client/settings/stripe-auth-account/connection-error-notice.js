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
 * @param {string} [props.message] Optional message override (an interpolate-components
 *                                 mixedString that may use the {{br /}} and {{Link}} tokens).
 *                                 Defaults to the generic connection-generation error.
 *
 * @return {JSX.Element} The rendered ConnectionErrorNotice component.
 */
const ConnectionErrorNotice = ( { message } = {} ) => {
	return (
		<InlineNotice isDismissible={ false } status="error">
			{ interpolateComponents( {
				mixedString:
					message ||
					__(
						'An issue occurred generating a connection to Stripe, please ensure your server has a valid SSL certificate and try again.{{br /}}For assistance, refer to our {{Link}}documentation{{/Link}}.',
						'woocommerce-gateway-stripe'
					),
				components: {
					br: <br />,
					Link: (
						<ExternalLink href="https://woocommerce.com/document/stripe/setup-and-configuration/connecting-to-stripe/" />
					),
				},
			} ) }
		</InlineNotice>
	);
};

export default ConnectionErrorNotice;
