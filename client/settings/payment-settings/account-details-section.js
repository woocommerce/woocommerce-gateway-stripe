import { __ } from '@wordpress/i18n';
import { React, useState } from 'react';
import { Button, Card, CardHeader, DropdownMenu } from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';
import styled from '@emotion/styled';
import CardBody from '../card-body';
import CardFooter from '../card-footer';
import Pill from '../../components/pill';
import AccountStatus from '../account-details';
import DisconnectStripeConfirmationModal from './disconnect-stripe-confirmation-modal';
import './style.scss';
import { useTestMode } from 'wcstripe/data';
import { useAccount } from 'wcstripe/data/account';

const HeaderDetails = styled.div`
	display: flex;
	margin: 0;
	font-size: 16px;

	h4 {
		margin: 0 4px 0 0;
	}
`;

const StripeAccountId = styled.span`
	font-size: 12px;
	color: #757575;
	margin-left: auto;
`;

// @todo - remove setModalType as prop
const AccountSettingsDropdownMenu = ( {
	setModalType,
	setKeepModalContent,
} ) => {
	// @todo - deconstruct setModalType from useModalType custom hook
	const [ isTestModeEnabled ] = useTestMode();
	const [
		isConfirmationModalVisible,
		setIsConfirmationModalVisible,
	] = useState( false );

	return (
		<>
			<DropdownMenu
				icon={ moreVertical }
				label={ __(
					'Edit details or disconnect account',
					'woocommerce-gateway-stripe'
				) }
				controls={ [
					{
						title: __(
							'Edit account keys',
							'woocommerce-gateway-stripe'
						),
						onClick: () =>
							setModalType( isTestModeEnabled ? 'test' : 'live' ),
					},
					{
						title: __( 'Disconnect', 'woocommerce-gateway-stripe' ),
						onClick: () => setIsConfirmationModalVisible( true ),
					},
				] }
			/>
			{ isConfirmationModalVisible && (
				<DisconnectStripeConfirmationModal
					onClose={ () => setIsConfirmationModalVisible( false ) }
					setKeepModalContent={ setKeepModalContent }
				/>
			) }
		</>
	);
};

// @todo - remove setModalType as prop
const AccountDetailsSection = ( { setModalType, setKeepModalContent } ) => {
	const [ isTestMode ] = useTestMode();
	const { data } = useAccount();
	const isTestModeEnabled = Boolean( data.testmode );

	return (
		<Card className="account-details">
			<CardHeader>
				<HeaderDetails>
					<h4>
						{ data.account?.email
							? data.account.email
							: __(
									'Account status',
									'woocommerce-gateway-stripe'
							  ) }
					</h4>

					{ isTestModeEnabled && (
						<Pill>
							{ __( 'Test Mode', 'woocommerce-gateway-stripe' ) }
						</Pill>
					) }
				</HeaderDetails>
				{ data.account?.id && (
					<StripeAccountId>{ data.account.id }</StripeAccountId>
				) }
				<AccountSettingsDropdownMenu
					setModalType={ setModalType }
					setKeepModalContent={ setKeepModalContent }
				/>
			</CardHeader>
			<CardBody>
				<AccountStatus />
			</CardBody>
			<CardFooter>
				<Button
					variant="secondary"
					onClick={ () =>
						setModalType( isTestMode ? 'test' : 'live' )
					}
				>
					{ __( 'Edit account keys', 'woocommerce-gateway-stripe' ) }
				</Button>
			</CardFooter>
		</Card>
	);
};

export default AccountDetailsSection;
