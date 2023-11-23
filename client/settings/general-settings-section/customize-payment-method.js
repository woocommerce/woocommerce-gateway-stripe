import { __ } from '@wordpress/i18n';
import React, { useState } from 'react';
import styled from '@emotion/styled';
import { Button, TextControl } from '@wordpress/components';
import { useIndividualPaymentMethodSettings } from 'wcstripe/data';

const ButtonWrapper = styled.div`
	display: flex;
	justify-content: flex-end;
	gap: 8px;
`;

const CustomizePaymentMethod = ( { method, onClose } ) => {
	const [
		individualPaymentMethodSettings,
		setIndividualPaymentMethodSettings,
	] = useIndividualPaymentMethodSettings();
	const { name, description } = individualPaymentMethodSettings[ method ];
	const [ methodName, setMethodName ] = useState( name );
	const [ methodDescription, setMethodDescription ] = useState( description );

	const onSave = () => {
		setIndividualPaymentMethodSettings( {
			...individualPaymentMethodSettings,
			[ method ]: {
				name: methodName,
				description: methodDescription,
			},
		} );
		onClose();
	};

	return (
		<div>
			<TextControl
				label={ __( 'Name', 'woocommerce-gateway-stripe' ) }
				value={ methodName }
				onChange={ setMethodName }
				help={ __(
					'Enter a name which customers will see during checkout.',
					'woocommerce-gateway-stripe'
				) }
			/>
			<TextControl
				label={ __( 'Description', 'woocommerce-gateway-stripe' ) }
				value={ methodDescription }
				onChange={ setMethodDescription }
				help={ __(
					'Describe how customers should use this payment method during checkout.',
					'woocommerce-gateway-stripe'
				) }
			/>
			<ButtonWrapper>
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'woocommerce-gateway-stripe' ) }
				</Button>
				<Button variant="secondary" onClick={ onSave }>
					{ __( 'Save changes', 'woocommerce-gateway-stripe' ) }
				</Button>
			</ButtonWrapper>
		</div>
	);
};

export default CustomizePaymentMethod;