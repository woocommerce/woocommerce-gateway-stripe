import { caution } from '@wordpress/icons';
import React from 'react';
import styled from '@emotion/styled';
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';

const NoticeContainer = styled.div`
	background-color: #fcf0f1;
	width: 100%;
	padding: 16px;
	margin-bottom: 10px;

	span {
		margin-right: 8px;
		vertical-align: middle;

		> svg {
			fill: #8a2424;
		}
	}
`;

export const ReconnectNotice = () => {
	return (
		<NoticeContainer>
			<span data-testid="help">
				<Icon icon={ caution } size="18" />
			</span>
			{ __(
				'Reconnect your Stripe account using the new authentication flow to avoid disruptions on your store.',
				'woocommerce-gateway-stripe'
			) }
		</NoticeContainer>
	);
};
