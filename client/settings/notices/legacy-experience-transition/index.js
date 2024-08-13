import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import '@wordpress/notices';
import styled from '@emotion/styled';
import React from 'react';
import { Button } from '@wordpress/components';
import { recordEvent } from 'wcstripe/tracking';

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

const DisableLegacyButton = styled( Button )`
	box-shadow: inset 0 0 0 1px #bd8600 !important;
	color: #bd8600 !important;
`;

const LearnMoreAnchor = styled.a`
	color: #bd8600 !important;
`;

const LegacyExperienceTransitionNotice = ( {
	isUpeEnabled,
	setIsUpeEnabled,
} ) => {
	const { createErrorNotice, createSuccessNotice } = useDispatch(
		'core/notices'
	);

	// The merchant already disabled the legacy experience. Nothing to do here.
	if ( isUpeEnabled ) {
		return null;
	}

	const handleDisableButtonClick = () => {
		const callback = async () => {
			try {
				await setIsUpeEnabled( true );

				recordEvent( 'wcstripe_legacy_experience_disabled', {
					source: 'payment-methods-tab-notice',
				} );

				createSuccessNotice(
					__(
						'New checkout experience enabled',
						'woocommerce-gateway-stripe'
					)
				);
			} catch ( err ) {
				createErrorNotice(
					__(
						'There was an error. Please reload the page and try again.',
						'woocommerce-gateway-stripe'
					)
				);
			}
		};

		// creating a separate callback so that the UI isn't blocked by the async call.
		callback();
	};

	return (
		<NoticeWrapper>
			<Message>
				<h3 data-testid="legacy-exp-title">
					{ __(
						'Enable the new checkout',
						'woocommerce-gateway-stripe'
					) }
				</h3>
				{ __(
					"You're using the legacy version of the Stripe extension which supports limited payment methods. Enable the new checkout to access over 40 popular payment methods and optimizations to increase conversion.",
					'woocommerce-gateway-stripe'
				) }
			</Message>
			<div>
				<DisableLegacyButton
					variant="secondary"
					onClick={ handleDisableButtonClick }
					data-testid="disable-legacy-button"
				>
					{ __(
						'Enable the new checkout',
						'woocommerce-gateway-stripe'
					) }
				</DisableLegacyButton>
				<LearnMoreAnchor
					href="https://woocommerce.com/document/stripe/admin-experience/new-checkout-experience/"
					className="components-button is-tertiary"
					target="_blank"
					rel="noreferrer"
				>
					{ __( 'Learn more', 'woocommerce-gateway-stripe' ) }
				</LearnMoreAnchor>
			</div>
		</NoticeWrapper>
	);
};

export default LegacyExperienceTransitionNotice;
