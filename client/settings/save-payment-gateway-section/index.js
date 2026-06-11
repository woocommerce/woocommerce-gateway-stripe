import { __ } from '@wordpress/i18n';
import React from 'react';
import styled from '@emotion/styled';
import { Button } from '@wordpress/components';
import SettingsSection from '../settings-section';
import { usePaymentGateway } from '../../data';

const SaveSectionWrapper = styled( SettingsSection )`
	text-align: right;
`;

const SavePaymentGatewaySection = () => {
	const { savePaymentGateway, isSaving, isLoading } = usePaymentGateway();

	return (
		<SaveSectionWrapper>
			<Button
				isPrimary
				isBusy={ isSaving }
				disabled={ isSaving || isLoading }
				onClick={ savePaymentGateway }
			>
				{ __( 'Save changes', 'woocommerce-gateway-stripe' ) }
			</Button>
		</SaveSectionWrapper>
	);
};

export default SavePaymentGatewaySection;
