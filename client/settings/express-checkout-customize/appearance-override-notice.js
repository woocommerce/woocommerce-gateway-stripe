import { ADMIN_URL, getSetting } from '@woocommerce/settings';
import React from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import styled from '@emotion/styled';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const StyledLink = styled.a`
	&:visited {
		box-shadow: none;
	}
	&:focus-visible {
		outline: 2px solid currentColor;
		outline-offset: 2px;
	}
`;

/**
 * Warns that the Cart & Checkout blocks' express payment section can override the appearance
 * settings on this page. Shown on every Customize express checkouts tab. Renders nothing when the
 * style isn't overridden, so callers can render it unconditionally.
 *
 * @param {Object}  props
 * @param {boolean} props.isOverridden Whether the block-level appearance override is in effect.
 */
const ExpressCheckoutAppearanceOverrideNotice = ( { isOverridden } ) => {
	if ( ! isOverridden ) {
		return null;
	}

	const checkoutPageId = getSetting( 'storePages' )?.checkout?.id;

	return (
		<Notice status="warning" isDismissible={ false }>
			{ interpolateComponents( {
				mixedString: __(
					'Some appearance settings may be overridden by the express payment section of the ' +
						'{{checkoutPageLink}}Cart & Checkout blocks{{/checkoutPageLink}}.',
					'woocommerce-gateway-stripe'
				),
				components: {
					checkoutPageLink: checkoutPageId ? (
						<StyledLink
							href={ `${ ADMIN_URL }post.php?post=${ checkoutPageId }&action=edit` }
							target="_blank"
							rel="noreferrer"
							onClick={ ( ev ) => {
								// Stop propagation is necessary so it doesn't trigger the tooltip click event.
								ev.stopPropagation();
							} }
						/>
					) : (
						<span />
					),
				},
			} ) }
		</Notice>
	);
};

export default ExpressCheckoutAppearanceOverrideNotice;
