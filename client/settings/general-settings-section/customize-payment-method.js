import { __ } from '@wordpress/i18n';
import React, { useState, useEffect } from 'react';
import styled from '@emotion/styled';
import { Button, TextControl } from '@wordpress/components';
import { isEqual } from 'lodash';
import {
	useCustomizePaymentMethodSettings,
	useEnabledPaymentMethodIds,
} from 'wcstripe/data';
import useConfirmNavigation from 'utils/use-confirm-navigation';

const ButtonWrapper = styled.div`
	display: flex;
	justify-content: flex-end;
	gap: 8px;
`;

const CustomizePaymentMethod = ( { method, onClose } ) => {
	const {
		individualPaymentMethodSettings,
		isCustomizing,
		customizePaymentMethod,
	} = useCustomizePaymentMethodSettings();
	const [ enabledPaymentMethodIds ] = useEnabledPaymentMethodIds();
	const { name, description, expiration } = individualPaymentMethodSettings[
		method
	];
	const [ methodName, setMethodName ] = useState( name );
	const [ methodDescription, setMethodDescription ] = useState( description );
	const [ methodExpiration, setMethodExpiration ] = useState( expiration );

	const isPristine =
		isEqual( name, methodName ) &&
		isEqual( description, methodDescription ) &&
		isEqual( expiration, methodExpiration );
	const displayPrompt = ! isPristine;
	const confirmationNavigationCallback = useConfirmNavigation(
		displayPrompt
	);

	useEffect( confirmationNavigationCallback, [
		displayPrompt,
		confirmationNavigationCallback,
	] );

	const onSave = async () => {
		const data = {
			...individualPaymentMethodSettings,
			[ method ]: {
				name: methodName,
				description: methodDescription,
				expiration: methodExpiration,
			},
		};
		await customizePaymentMethod(
			method,
			enabledPaymentMethodIds.includes( method ),
			data
		);
		onClose( data );
	};

	return (
		<div>
			<TextControl
				label={ __( 'Name', 'woocommerce-gateway-stripe' ) }
				value={ methodName }
				onChange={ setMethodName }
				disabled={ isCustomizing }
				help={ __(
					'Enter a name which customers will see during checkout.',
					'woocommerce-gateway-stripe'
				) }
			/>
			<TextControl
				label={ __( 'Description', 'woocommerce-gateway-stripe' ) }
				value={ methodDescription }
				onChange={ setMethodDescription }
				disabled={ isCustomizing }
				help={ __(
					'Describe how customers should use this payment method during checkout.',
					'woocommerce-gateway-stripe'
				) }
			/>
			{ methodExpiration && (
				<TextControl
					label={ __( 'Expiration', 'woocommerce-gateway-stripe' ) }
					value={ methodExpiration }
					onChange={ setMethodExpiration }
					disabled={ isCustomizing }
					help={ __(
						'Expiration in number of days for the voucher.',
						'woocommerce-gateway-stripe'
					) }
				/>
			) }
			<ButtonWrapper>
				<Button
					variant="tertiary"
					disabled={ isCustomizing }
					onClick={ onClose }
				>
					{ __( 'Cancel', 'woocommerce-gateway-stripe' ) }
				</Button>
				<Button
					variant="secondary"
					disabled={ isCustomizing }
					onClick={ onSave }
				>
					{ __( 'Save changes', 'woocommerce-gateway-stripe' ) }
				</Button>
			</ButtonWrapper>
		</div>
	);
};

export default CustomizePaymentMethod;
