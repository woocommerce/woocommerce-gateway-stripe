import React from 'react';
import classNames from 'classnames';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { WebhookInformation } from 'wcstripe/components/webhook-information';
import useWebhookStateMessage from 'wcstripe/settings/account-details/use-webhook-state-message';
import WarningIcon from 'wcstripe/components/webhook-description/warning-icon';
import './style.scss';

export const WebhookDescription = ( { isWebhookEnabled } ) => {
	const { code, message, requestStatus, refreshMessage } =
		useWebhookStateMessage();
	const isWarningMessage = code === 3 || code === 4;
	const isSuccessMessage = code === 1;
	const isSuccessMessageWithSecret = isSuccessMessage && isWebhookEnabled;
	const classes = classNames( 'wc-stripe-webhook-description__content', {
		expanded: isWebhookEnabled,
		warning: isWarningMessage,
	} );

	return (
		<div className="wc-stripe-webhook-description">
			{ ! isWebhookEnabled && <WebhookInformation /> }
			<div className={ classes }>
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
			</div>
		</div>
	);
};
