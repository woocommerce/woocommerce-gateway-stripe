import { __ } from '@wordpress/i18n';
import styled from '@emotion/styled';
import React from 'react';
import { Button } from '@wordpress/components';
import { isEmpty } from 'lodash';
import { useGetCapabilities } from 'wcstripe/data/account';
import { useGetAvailablePaymentMethodIds } from 'wcstripe/data';

const NoticeWrapper = styled.div`
	display: flex;
	flex-direction: column;
	padding: 12px 16px;
	align-items: flex-start;
	gap: 12px;
	margin: 0 0 24px 0;
	background: #fcf9e8;
`;

const Message = styled.div`
	color: #1e1e1e;
	font-size: 13px;
`;

const ActivationButton = styled( Button )`
	box-shadow: inset 0 0 0 1px #bd8600 !important;
	color: #bd8600 !important;
`;

const AccountActivationNotice = () => {
	const capabilities = useGetCapabilities();
	const upePaymentMethods = useGetAvailablePaymentMethodIds();

	const requiresActivation =
		isEmpty( capabilities ) ||
		upePaymentMethods.some( ( method ) => {
			const capabilityStatus = capabilities[ `${ method }_payments` ];
			return (
				capabilityStatus === 'pending' ||
				capabilityStatus === 'inactive'
			);
		} );

	if ( ! requiresActivation ) {
		return null;
	}

	return (
		<NoticeWrapper>
			<Message>
				{ __(
					'Payment methods require activation in your Stripe dashboard.',
					'woocommerce-gateway-stripe'
				) }{ ' ' }
			</Message>
			<ActivationButton
				variant="secondary"
				href="https://dashboard.stripe.com/settings/payments"
				target="_blank"
				rel="noreferrer"
			>
				{ __(
					'Activate stripe account',
					'woocommerce-gateway-stripe'
				) }
			</ActivationButton>
		</NoticeWrapper>
	);
};

export default AccountActivationNotice;
