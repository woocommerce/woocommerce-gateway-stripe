import { React, useEffect, useRef, useState } from 'react';
import { moreVertical } from '@wordpress/icons';
import styled from '@emotion/styled';
import { getQuery } from '@woocommerce/navigation';
import CardBody from '../card-body';
import CardFooter from '../card-footer';
import Pill from '../../components/pill';
import AccountStatus from '../account-details';
import DisconnectStripeConfirmationModal from './disconnect-stripe-confirmation-modal';
import { Button, Card, CardHeader, DropdownMenu } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import './style.scss';
import { useTestMode } from 'wcstripe/data';
import { useAccount } from 'wcstripe/data/account';
import { recordEvent } from 'wcstripe/tracking';

const HeaderDetails = styled.div`
	display: flex;
	margin: 0;
	font-size: 16px;
	h4 {
		align-content: center;
		margin: 0 4px 0 0;
	}
`;

const AccountIdentity = styled.div`
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	margin-left: auto;
	line-height: 1.2;
`;

const StripeAccountName = styled.span`
	font-size: 15px;
	color: #1e1e1e;
	font-weight: 600;
`;

const StripeAccountId = styled.span`
	font-size: 12px;
	color: #757575;
`;

// @todo - remove setModalType as prop
const AccountSettingsDropdownMenu = ( {
	setModalType,
	setKeepModalContent,
} ) => {
	// @todo - deconstruct setModalType from useModalType custom hook
	const [ isTestModeEnabled ] = useTestMode();
	const { refreshAccount } = useDispatch( 'wc/stripe' );
	const { createSuccessNotice } = useDispatch( 'core/notices' );
	const [ isConfirmationModalVisible, setIsConfirmationModalVisible ] =
		useState( false );

	const handleRefreshAccount = async () => {
		await refreshAccount();
		createSuccessNotice(
			__(
				'Account details reloaded successfully.',
				'woocommerce-gateway-stripe'
			)
		);
	};

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
							'Configure connection',
							'woocommerce-gateway-stripe'
						),
						onClick: () =>
							setModalType( isTestModeEnabled ? 'test' : 'live' ),
					},
					{
						title: __(
							'Refresh account details',
							'woocommerce-gateway-stripe'
						),
						onClick: handleRefreshAccount,
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
	const headingRef = useRef( null );
	const [ isTestMode ] = useTestMode();
	const { data } = useAccount();
	const oauthConnected = isTestMode
		? data?.oauth_connections?.test?.connected
		: data?.oauth_connections?.live?.connected;

	const handleButtonClick = () => {
		const mode = isTestMode ? 'test' : 'live';
		if ( ! oauthConnected ) {
			recordEvent( 'wcstripe_reconnect_button_click', {
				source: 'account_details_section',
				mode,
			} );
		}

		setModalType( mode );
	};

	useEffect( () => {
		if ( ! headingRef.current ) {
			return;
		}

		const { highlight } = getQuery();
		if ( highlight === 'account-details' ) {
			headingRef.current.scrollIntoView( {
				behavior: 'smooth',
				block: 'start',
			} );
		}
	}, [ headingRef ] );

	return (
		<Card className="account-details">
			<CardHeader>
				<HeaderDetails>
					<h4 ref={ headingRef }>
						{ data.account?.email
							? data.account.email
							: __(
									'Account status',
									'woocommerce-gateway-stripe'
							  ) }
					</h4>

					{ isTestMode && (
						<Pill>
							{ __( 'Test Mode', 'woocommerce-gateway-stripe' ) }
						</Pill>
					) }
				</HeaderDetails>
				{ data.account?.id && (
					<AccountIdentity>
						{ ( data.account?.settings?.dashboard?.display_name ||
							data.account?.business_profile?.name ) && (
							<StripeAccountName>
								{ data.account.settings?.dashboard
									?.display_name ||
									data.account.business_profile.name }
							</StripeAccountName>
						) }
						<StripeAccountId>{ data.account.id }</StripeAccountId>
					</AccountIdentity>
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
					id="btn-configure-connection"
					onClick={ handleButtonClick }
				>
					{ __(
						'Configure connection',
						'woocommerce-gateway-stripe'
					) }
				</Button>
			</CardFooter>
		</Card>
	);
};

export default AccountDetailsSection;
