import { React, useState } from 'react';
import CardBody from '../card-body';
import { AccountKeysModal } from './account-keys-modal';
import TestModeCheckbox from './test-mode-checkbox';
import { Card, CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useIsStripeEnabled } from 'wcstripe/data';

const GeneralSettingsSection = ( { setKeepModalContent } ) => {
	const [ isStripeEnabled, setIsStripeEnabled ] = useIsStripeEnabled();
	const [ modalType, setModalType ] = useState( '' );

	const handleModalDismiss = () => {
		setModalType( '' );
	};

	const handleCheckboxChange = ( hasBeenChecked ) => {
		setIsStripeEnabled( hasBeenChecked );
	};

	return (
		<>
			{ modalType && (
				<AccountKeysModal
					type={ modalType }
					onClose={ handleModalDismiss }
					setKeepModalContent={ setKeepModalContent }
				/>
			) }
			<Card>
				<CardBody>
					<CheckboxControl
						checked={ isStripeEnabled }
						onChange={ handleCheckboxChange }
						label={ __(
							'Enable Stripe',
							'woocommerce-gateway-stripe'
						) }
						help={ __(
							'When enabled, payment methods powered by Stripe will appear on checkout.',
							'woocommerce-gateway-stripe'
						) }
					/>
					<TestModeCheckbox />
				</CardBody>
			</Card>
		</>
	);
};

export default GeneralSettingsSection;
