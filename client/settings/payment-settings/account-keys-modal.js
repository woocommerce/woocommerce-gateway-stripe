import { __ } from '@wordpress/i18n';
import { React, useRef, useState } from 'react';
import styled from '@emotion/styled';
import { TabPanel } from '@wordpress/components';
import { useAccountKeys } from 'wcstripe/data/account-keys';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import StripeAuthAccount from 'wcstripe/settings/stripe-auth-account';

const Form = ( { formRef, testMode } ) => {
	return (
		<form ref={ formRef }>
			<StripeAuthAccount testMode={ testMode } />
		</form>
	);
};

const StyledTabPanel = styled( TabPanel )`
	margin: 0 24px 24px;
`;

const StyledConfirmationModal = styled( ConfirmationModal )`
	.components-modal__content {
		padding: 0;
	}
	.components-modal__header {
		padding: 0 24px;
		margin: 0;
	}
	.components-tab-panel__tabs {
		background-color: #f1f1f1;
		margin: 0 -24px 24px;
	}
	.wcstripe-inline-notice {
		margin-top: 0;
		margin-bottom: 0;
	}
	.wcstripe-confirmation-modal__separator {
		margin: 0;
	}
	.wcstripe-confirmation-modal__footer {
		padding: 16px;
	}
`;

export const AccountKeysModal = ( { type, onClose } ) => {
	const [ openTab, setOpenTab ] = useState( type );
	const { updateIsValidAccountKeys } = useAccountKeys();
	const formRef = useRef( null );
	const testFormRef = useRef( null );
	const testMode = openTab === 'test';

	const onCloseHelper = () => {
		// Reset AccountKeysConnectionStatus to default state.
		updateIsValidAccountKeys( null );
		onClose();
	};

	const onTabSelect = ( tabName ) => {
		// Reset AccountKeysConnectionStatus to default state.
		updateIsValidAccountKeys( null );
		setOpenTab( tabName );
	};

	return (
		<StyledConfirmationModal
			onRequestClose={ onCloseHelper }
			title={
				testMode
					? __(
							'Test Stripe account & webhooks',
							'woocommerce-gateway-stripe'
					  )
					: __(
							'Live Stripe account & webhooks',
							'woocommerce-gateway-stripe'
					  )
			}
		>
			<StyledTabPanel
				initialTabName={ type }
				onSelect={ onTabSelect }
				tabs={ [
					{
						name: 'live',
						title: __( 'Live', 'woocommerce-gateway-stripe' ),
						className: 'live-tab',
					},
					{
						name: 'test',
						title: __( 'Test', 'woocommerce-gateway-stripe' ),
						className: 'test-tab',
					},
				] }
			>
				{ () => (
					<Form
						formRef={ testMode ? testFormRef : formRef }
						testMode={ testMode }
					/>
				) }
			</StyledTabPanel>
		</StyledConfirmationModal>
	);
};
