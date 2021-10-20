import { __ } from '@wordpress/i18n';
import { React, useState } from 'react';
import { Button, CheckboxControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { useTestMode } from 'wcstripe/data';
import { useAccountKeys } from 'wcstripe/data/account-keys';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import InlineNotice from 'wcstripe/components/inline-notice';

const MissingAccountKeysModal = ( { type, onClose } ) => {
	const [ , setTestMode ] = useTestMode();

	const handleSave = () => {
		setTestMode( type === 'test' );
		onClose();
	};

	return (
		<ConfirmationModal
			onRequestClose={ onClose }
			actions={
				<>
					<Button isSecondary onClick={ onClose }>
						{ __( 'Cancel', 'woocommerce-gateway-stripe' ) }
					</Button>
					<Button isPrimary onClick={ handleSave }>
						{ __( 'Save changes', 'woocommerce-gateway-stripe' ) }
					</Button>
				</>
			}
			title={
				type === 'test'
					? __(
							'Edit test account keys & webhooks',
							'woocommerce-gateway-stripe'
					  )
					: __(
							'Edit live account keys & webhooks',
							'woocommerce-gateway-stripe'
					  )
			}
		>
			<InlineNotice isDismissible={ false }>
				{ type === 'test'
					? interpolateComponents( {
							mixedString: __(
								"To enable the test mode, get the test account keys from your {{accountLink}}Stripe Account{{/accountLink}} (we'll save them for you so you won't have to do this every time).",
								'woocommerce-gateway-stripe'
							),
							components: {
								accountLink: (
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a href="https://dashboard.stripe.com/test/apikeys" />
								),
							},
					  } )
					: interpolateComponents( {
							mixedString: __(
								"To enable the live mode, get the account keys from your {{accountLink}}Stripe Account{{/accountLink}} (we'll save them for you so you won't have to do this every time).",
								'woocommerce-gateway-stripe'
							),
							components: {
								accountLink: (
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a href="https://dashboard.stripe.com/apikeys" />
								),
							},
					  } ) }
			</InlineNotice>
		</ConfirmationModal>
	);
};

const TestModeCheckbox = () => {
	const [ modalType, setModalType ] = useState( '' );
	const [ isTestModeEnabled, setTestMode ] = useTestMode();
	const { accountKeys } = useAccountKeys();

	const handleCheckboxChange = ( isChecked ) => {
		// are we enabling test mode without the necessary keys?
		if (
			isChecked &&
			( ! accountKeys.test_publishable_key ||
				! accountKeys.test_secret_key ||
				! accountKeys.test_webhook_secret )
		) {
			setModalType( 'test' );
			return;
		}

		// are we enabling live mode without the necessary keys?
		if (
			! isChecked &&
			( ! accountKeys.publishable_key ||
				! accountKeys.secret_key ||
				! accountKeys.webhook_secret )
		) {
			setModalType( 'live' );
			return;
		}

		// all keys are present, GTG
		setTestMode( isChecked );
	};

	const handleModalDismiss = () => {
		setModalType( '' );
	};

	return (
		<>
			{ modalType && (
				<MissingAccountKeysModal
					type={ modalType }
					onClose={ handleModalDismiss }
				/>
			) }
			<CheckboxControl
				checked={ isTestModeEnabled }
				onChange={ handleCheckboxChange }
				label={ __( 'Enable test mode', 'woocommerce-gateway-stripe' ) }
				help={ interpolateComponents( {
					mixedString: __(
						'Use {{testCardNumbersLink}}test card numbers{{/testCardNumbersLink}} to simulate various transactions. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
						'woocommerce-gateway-stripe'
					),
					components: {
						testCardNumbersLink: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a href="https://stripe.com/docs/testing#cards" />
						),
						learnMoreLink: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a href="https://stripe.com/docs/testing" />
						),
					},
				} ) }
			/>
		</>
	);
};

export default TestModeCheckbox;
