/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';

/**
 * External dependencies
 */
import CardFooter from 'wcstripe/settings/card-footer';

const SectionFooter = () => (
	<CardFooter>
		<ExternalLink
			className="components-button is-secondary"
			href="https://dashboard.stripe.com/account/payments/settings"
		>
			{ __( 'Get more payment methods', 'woocommerce-gateway-stripe' ) }
		</ExternalLink>
	</CardFooter>
);

export default SectionFooter;
