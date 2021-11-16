import { __ } from '@wordpress/i18n';
import { React, useState, useContext } from 'react';
import {
	Button,
	Card,
	CheckboxControl,
	TextControl,
} from '@wordpress/components';
import styled from '@emotion/styled';
import CardBody from '../card-body';
import CardFooter from '../card-footer';
import { AccountKeysModal } from './account-keys-modal';
import TestModeCheckbox from './test-mode-checkbox';
import {
	useTestMode,
	useIsStripeEnabled,
	useTitle,
	useUpeTitle,
	useDescription,
} from 'wcstripe/data';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

const StyledCard = styled( Card )`
	margin-bottom: 12px;
`;

const Description = styled.div`
	color: #757575;
	font-size: 12px;
`;

const GeneralSettingsSection = () => {
	const [ isTestMode ] = useTestMode();
	const [ isStripeEnabled, setIsStripeEnabled ] = useIsStripeEnabled();
	const [ title, setTitle ] = useTitle();
	const [ upeTitle, setUpeTitle ] = useUpeTitle();
	const [ description, setDescription ] = useDescription();
	const [ modalType, setModalType ] = useState( '' );
	const { isUpeEnabled } = useContext( UpeToggleContext );

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
						checked={ isStripeEnabled }
						onChange={ setIsStripeEnabled }
						label={ __(
							'Enable Stripe',
							'woocommerce-gateway-stripe'
						) }
						help={ __(
							'When enabled, payment methods powered by Stripe will appear on checkout.',
							'woocommerce-gateway-stripe'
						) }
					/>
					<h4>
						{ __(
							'Display settings',
							'woocommerce-gateway-stripe'
						) }
					</h4>
					<Description>
						{ isUpeEnabled
							? __(
									'Enter the payment method name that will be displayed at checkout when there are multiple available payment methods.',
									'woocommerce-gateway-stripe'
							  )
							: __(
									'Enter payment method details that will be displayed at checkout, in the order confirmation screen and in the order notes.',
									'woocommerce-gateway-stripe'
							  ) }
					</Description>
					<TextControl
						label={ __( 'Name', 'woocommerce-gateway-stripe' ) }
						value={ isUpeEnabled ? upeTitle : title }
						onChange={ isUpeEnabled ? setUpeTitle : setTitle }
					/>
					{ ! isUpeEnabled && (
						<TextControl
							label={ __(
								'Description',
								'woocommerce-gateway-stripe'
							) }
							value={ description }
							onChange={ setDescription }
						/>
					) }
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
