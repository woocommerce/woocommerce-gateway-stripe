import { __ } from '@wordpress/i18n';
import { React, useState } from 'react';
import { CheckboxControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { AccountKeysModal } from './account-keys-modal';
import { useTestMode } from 'wcstripe/data';
import { useAccountKeys } from 'wcstripe/data/account-keys';

const TestModeCheckbox = () => {
	const [ modalType, setModalType ] = useState( '' );
	const [ isTestModeEnabled, setTestMode ] = useTestMode();
	const { accountKeys } = useAccountKeys();

	const missingLiveKeys =
		! accountKeys.publishable_key || ! accountKeys.secret_key;
	const missingTestKeys =
		! accountKeys.test_publishable_key || ! accountKeys.test_secret_key;

	const handleCheckboxChange = ( isChecked ) => {
		// are we enabling test mode without the necessary keys?
		if ( isChecked && missingTestKeys ) {
			setModalType( 'test' );
			return;
		}

		// are we enabling live mode without the necessary keys?
		if ( ! isChecked && missingLiveKeys ) {
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
				<AccountKeysModal
					type={ modalType }
					onClose={ handleModalDismiss }
					forcePageReloadOnSave
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
