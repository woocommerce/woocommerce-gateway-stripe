import { __ } from '@wordpress/i18n';
import React, { useState } from 'react';
import styled from '@emotion/styled';
import { Popover as PopoverComponent } from '@wordpress/components';
import icon from './icon.svg';

const Icon = styled.img`
	height: 14px;
	width: 14px;
	cursor: pointer;
`;

const IconWrapper = styled.span`
	display: inline-flex;
	align-items: center;
`;

const StyledPopover = styled( PopoverComponent )`
	top: -11px !important;

	.components-popover__content {
		border-radius: 4px;
		box-shadow: 0px 2px 6px 0px rgba( 0, 0, 0, 0.05 );
		padding: 5px 10px;
		background-color: #000000;
		color: #ffffff;
	}

	@media ( min-width: 660px ) {
		.components-popover__content {
			width: auto;
		}
	}
`;

const IconComponent = ( { children, ...props } ) => (
	<IconWrapper { ...props }>
		<Icon src={ icon } alt="" />
		{ children }
	</IconWrapper>
);

const RecurringPaymentIcon = () => {
	const [ isVisible, setIsVisible ] = useState( false );

	const toggleVisible = () => {
		setIsVisible( ( state ) => ! state );
	};

	return (
		<IconComponent onClick={ toggleVisible }>
			{ isVisible && (
				<StyledPopover
					animate={ true }
					placement="top"
					variant="toolbar"
					onFocusOutside={ () => setIsVisible( false ) }
				>
					{ __(
						'Supports recurring payments',
						'woocommerce-gateway-stripe'
					) }
				</StyledPopover>
			) }
		</IconComponent>
	);
};

export default RecurringPaymentIcon;
