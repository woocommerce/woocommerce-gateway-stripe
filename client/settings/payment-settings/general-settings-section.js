import { __ } from '@wordpress/i18n';
import { React, useState, useContext } from 'react';
import { Card, CheckboxControl } from '@wordpress/components';
import CardBody from '../card-body';
import { AccountKeysModal } from './account-keys-modal';
import TestModeCheckbox from './test-mode-checkbox';
import { useIsStripeEnabled, useEnabledPaymentMethodIds } from 'wcstripe/data';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';
import { PAYMENT_METHOD_CARD } from 'wcstripe/stripe-utils/constants';

const GeneralSettingsSection = ( { setKeepModalContent } ) => {
	const [ isStripeEnabled, setIsStripeEnabled ] = useIsStripeEnabled();
	const [
		enabledPaymentMethods,
		setEnabledPaymentMethods,
	] = useEnabledPaymentMethodIds();
	const [ modalType, setModalType ] = useState( '' );
	const { isUpeEnabled } = useContext( UpeToggleContext );

	const handleModalDismiss = () => {
		setModalType( '' );
	};

	const handleCheckboxChange = ( hasBeenChecked ) => {
		setIsStripeEnabled( hasBeenChecked );

		// In legacy mode (UPE disabled), Stripe refers to the card payment method.
		// So if Stripe is disabled, card should be excluded from the enabled methods list and vice versa.
		if ( ! isUpeEnabled ) {
			if (
				! hasBeenChecked &&
				enabledPaymentMethods.includes( PAYMENT_METHOD_CARD )
			) {
				setEnabledPaymentMethods(
					enabledPaymentMethods.filter(
						( m ) => m !== PAYMENT_METHOD_CARD
					)
				);
			} else if (
				hasBeenChecked &&
				! enabledPaymentMethods.includes( PAYMENT_METHOD_CARD )
			) {
				setEnabledPaymentMethods( [
					...enabledPaymentMethods,
					PAYMENT_METHOD_CARD,
				] );
			}
		}
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
