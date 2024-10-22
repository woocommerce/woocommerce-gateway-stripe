import { __ } from '@wordpress/i18n';
import React from 'react';
import styled from '@emotion/styled';
import { Icon, info } from '@wordpress/icons';
import Pill from 'wcstripe/components/pill';
import Popover from 'wcstripe/components/popover';

const StyledPill = styled( Pill )`
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

const PaymentMethodDeprecationPill = () => {
	return (
		<>
			<StyledPill>
				{ __( 'Deprecated', 'woocommerce-gateway-stripe' ) }

				<Popover
					BaseComponent={ IconComponent }
					content={ __(
						'This payment method is deprecated on the legacy checkout as of Oct, 29th 2024.'
					) }
				/>
			</StyledPill>
		</>
	);
};

export default PaymentMethodDeprecationPill;
