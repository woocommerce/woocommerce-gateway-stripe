import { __ } from '@wordpress/i18n';
import { React, useState } from 'react';
import { Button, Card, CheckboxControl } from '@wordpress/components';
import styled from '@emotion/styled';
import CardBody from '../card-body';
import CardFooter from '../card-footer';
import { AccountKeysModal } from './account-keys-modal';
import TestModeCheckbox from './test-mode-checkbox';
import { useTestMode } from 'wcstripe/data';

const StyledCard = styled( Card )`
	margin-bottom: 12px;
`;

const GeneralSettingsSection = () => {
	const [ enableStripe, setEnableStripe ] = useState( false );
	const [ modalType, setModalType ] = useState( '' );
	const [ isTestMode ] = useTestMode();

	const handleModalDismiss = () => {
		setModalType( '' );
	};

	return (
		<>
			{ modalType && (
				<AccountKeysModal
					type={ modalType }
					onClose={ handleModalDismiss }
				/>
			) }
			<StyledCard>
				<CardBody>
					<CheckboxControl
						checked={ enableStripe }
						onChange={ setEnableStripe }
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
					<Button
						isSecondary
						onClick={ () =>
							setModalType( isTestMode ? 'test' : 'live' )
						}
					>
						{ __(
							'Edit account keys',
							'woocommerce-gateway-stripe'
						) }
					</Button>
				</CardFooter>
			</StyledCard>
		</>
	);
};

export default GeneralSettingsSection;
