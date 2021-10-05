import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { useCallback } from '@wordpress/element';
import React, { useState } from 'react';
import styled from '@emotion/styled';
import { Modal, Button } from '@wordpress/components';
import { OPTIONS_STORE_NAME } from '@woocommerce/data';

const INFORMATION_OVERLAY_OPTION = 'wc_stripe_show_information_overlay';

const InformationOverlayWrapper = styled( Modal )`
	.components-modal__content {
		max-width: 400px;

		@media ( max-width: 660px ) {
			max-width: inherit;
		}
	}

	.components-modal__header {
		border-bottom: none;
		margin-bottom: 0;
	}
`;

const Actions = styled.div`
	margin-top: 16px;
`;

const InformationOverlay = () => {
	const [ isOverlayVisible, setIsOverlayVisible ] = useState( true );
	const { updateOptions } = useDispatch( OPTIONS_STORE_NAME );

	const handleDismiss = useCallback( () => {
		updateOptions( {
			[ INFORMATION_OVERLAY_OPTION ]: 'no',
		} );
		setIsOverlayVisible( false );
	}, [ updateOptions ] );

	if ( ! isOverlayVisible ) {
		return null;
	}

	return (
		<InformationOverlayWrapper
			title={ __(
				'View your Stripe payment methods',
				'woocommerce-gateway-stripe'
			) }
			onRequestClose={ () => handleDismiss() }
		>
			{ __(
				'In the new payment management experience, you can view and manage all supported Stripe-powered payment methods in a single place.',
				'woocommerce-gateway-stripe'
			) }
			<Actions>
				<Button onClick={ () => handleDismiss() } isPrimary>
					Got it
				</Button>
			</Actions>
		</InformationOverlayWrapper>
	);
};

export default InformationOverlay;
