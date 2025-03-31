import { __ } from '@wordpress/i18n';
import React from 'react';
import styled from '@emotion/styled';
import interpolateComponents from 'interpolate-components';
import { Icon, info } from '@wordpress/icons';
import Popover from 'wcstripe/components/popover';

const StyledPill = styled.span`
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 4px 8px;
	border: 1px solid #fcf9e8;
	border-radius: 2px;
	background-color: #fcf9e8;
	color: #674600;
	font-size: 12px;
	font-weight: 400;
	line-height: 16px;
	width: fit-content;
`;

const StyledLink = styled.a`
	&:focus,
	&:visited {
		box-shadow: none;
	}
`;

const IconWrapper = styled.span`
	height: 16px;
	cursor: pointer;
`;

const AlertIcon = styled( Icon )`
	fill: #674600;
`;

const IconComponent = ( { children, ...props } ) => (
	<IconWrapper { ...props }>
		<AlertIcon icon={ info } size="16" />
		{ children }
	</IconWrapper>
);

const ExpressCheckoutInaccurateTaxesPill = ( { taxBasedOn } ) => {
	if ( taxBasedOn === 'billing' ) {
		return (
			<StyledPill>
				{ __( 'Tax-based limitations', 'woocommerce-gateway-stripe' ) }
				<Popover
					BaseComponent={ IconComponent }
					content={ interpolateComponents( {
						mixedString: __(
							'Express Checkout is not available for virtual products when taxes are based on billing address. {{taxSettingsLink}}Manage tax settings{{/taxSettingsLink}}',
							'woocommerce-gateway-stripe'
						),
						components: {
							taxSettingsLink: (
								<StyledLink
									href="/wp-admin/admin.php?page=wc-settings&tab=tax"
									target="_blank"
									rel="noreferrer"
									onClick={ ( ev ) => {
										// Stop propagation is necessary so it doesn't trigger the tooltip click event.
										ev.stopPropagation();
									} }
								/>
							),
						},
					} ) }
				/>
			</StyledPill>
		);
	}

	return null;
};

export default ExpressCheckoutInaccurateTaxesPill;
