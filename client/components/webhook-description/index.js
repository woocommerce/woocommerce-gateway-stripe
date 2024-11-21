import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import React from 'react';
import styled from '@emotion/styled';
import { WebhookInformation } from 'wcstripe/components/webhook-information';
import useWebhookStateMessage from 'wcstripe/settings/account-details/use-webhook-state-message';
import WarningIcon from 'wcstripe/components/webhook-description/warning-icon';

const WebhookDescriptionWrapper = styled.div`
	font-size: 12px;
	font-style: normal;
	color: rgb( 117, 117, 117 );

	> span {
		align-self: center;
	}
`;

const WebhookDescriptionInner = styled.div`
	display: flex;
	align-items: flex-start;

	&.warning {
		background-color: #fcf9e8;
		color: #1e1e1e;
		padding: 12px 15px 12px 12px;
	}

	> p {
		margin: 0;
	}
`;

export const WebhookDescription = ( { isWebhookEnabled } ) => {
	const {
		code,
		message,
		requestStatus,
		refreshMessage,
	} = useWebhookStateMessage();
	const isWarningMessage = code === 3 || code === 4;
	const isSuccessMessage = code === 1;
	const isSuccessMessageWithSecret = isSuccessMessage && isWebhookEnabled;
	const webhookDescriptionClassesAr = [];
	if ( isWebhookEnabled ) {
		webhookDescriptionClassesAr.push( 'expanded' );
	}
	if ( isWarningMessage ) {
		webhookDescriptionClassesAr.push( 'warning' );
	}

	return (
		<WebhookDescriptionWrapper>
			{ ! isWebhookEnabled && <WebhookInformation /> }
			<WebhookDescriptionInner
				className={ webhookDescriptionClassesAr.join( ' ' ) }
			>
				{ isWarningMessage && <WarningIcon /> }
				{ ( ! isSuccessMessage || isSuccessMessageWithSecret ) && (
					<p>
						{ message }{ ' ' }
						<Button
							disabled={ requestStatus === 'pending' }
							onClick={ refreshMessage }
							isBusy={ requestStatus === 'pending' }
							isLink
						>
							{ __( 'Refresh', 'woocommerce-gateway-stripe' ) }
						</Button>
					</p>
				) }
			</WebhookDescriptionInner>
		</WebhookDescriptionWrapper>
	);
};
