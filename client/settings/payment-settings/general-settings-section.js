import { __ } from '@wordpress/i18n';
import { React, useState } from 'react';
import { Button, Card, CheckboxControl } from '@wordpress/components';
import styled from '@emotion/styled';
import CardBody from '../card-body';
import CardFooter from '../card-footer';
import TestModeCheckbox from './test-mode-checkbox';

const StyledCard = styled( Card )`
	margin-bottom: 12px;
`;

const GeneralSettingsSection = () => {
	const [ enableStripe, handleEnableStripeChange ] = useState( false );

	return (
		<StyledCard>
			<CardBody>
				<CheckboxControl
					checked={ enableStripe }
					onChange={ handleEnableStripeChange }
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
			<CardFooter>
				<Button isSecondary href="?TODO">
					{ __( 'Edit account keys', 'woocommerce-gateway-stripe' ) }
				</Button>
			</CardFooter>
		</StyledCard>
	);
};

export default GeneralSettingsSection;
