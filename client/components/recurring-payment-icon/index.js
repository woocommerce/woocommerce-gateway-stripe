import React from 'react';
import styled from '@emotion/styled';
import icon from './icon.svg';
import { __ } from '@wordpress/i18n';
import Tooltip from 'wcstripe/components/tooltip';

const Icon = styled.img`
	height: 14px;
	width: 14px;
`;

const StyledTooltip = styled( Tooltip )`
	border-radius: 4px;
	padding: 5px 10px;
`;

const RecurringPaymentIcon = () => {
	return (
		<StyledTooltip
			content={ __(
				'Supports recurring payments',
				'woocommerce-gateway-stripe'
			) }
		>
			<Icon src={ icon } alt="" />
		</StyledTooltip>
	);
};

export default RecurringPaymentIcon;
