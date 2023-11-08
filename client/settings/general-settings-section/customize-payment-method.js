import { __ } from '@wordpress/i18n';
import React from 'react';
import styled from '@emotion/styled';
import { Button, TextControl } from '@wordpress/components';

const ButtonWrapper = styled.div`
	display: flex;
	justify-content: flex-end;
	gap: 8px;
`;

const CustomizePaymentMethod = ( { method, onCancel } ) => {
	// eslint-disable-next-line @wordpress/i18n-no-variables
	const description = __(
		`You will be redirected to ${ method }.`,
		'woocommerce-gateway-stripe'
	);

	const onSave = () => {
		// todo: save method
	};

	return (
		<div>
			<TextControl
				label={ __( 'Name', 'woocommerce-gateway-stripe' ) }
				value={ method }
				onChange={ () => {} }
				help={ __(
					'Enter a name which customers will see during checkout.',
					'woocommerce-gateway-stripe'
				) }
			/>
			<TextControl
				label={ __( 'Description', 'woocommerce-gateway-stripe' ) }
				value={ description }
				onChange={ () => {} }
				help={ __(
					'Describe how customers should use this payment method during checkout.',
					'woocommerce-gateway-stripe'
				) }
			/>
			<ButtonWrapper>
				<Button variant="tertiary" onClick={ onCancel }>
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
