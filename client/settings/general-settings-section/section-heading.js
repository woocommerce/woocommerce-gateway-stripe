import { __ } from '@wordpress/i18n';
import React, { useContext } from 'react';
import styled from '@emotion/styled';
import { Button, CardHeader, DropdownMenu } from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';
import DisableUpeConfirmationModal from './disable-upe-confirmation-modal';
import Pill from 'wcstripe/components/pill';
import { useAccount } from 'wcstripe/data/account';
import {
	useGetAvailablePaymentMethodIds,
	useGetOrderedPaymentMethodIds,
} from 'wcstripe/data';
import useToggle from 'wcstripe/hooks/use-toggle';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

const StyledHeader = styled( CardHeader )`
	justify-content: space-between;

	.components-dropdown-menu__toggle.has-icon {
		padding: 0;
		min-width: unset;
	}

	button.components-dropdown-menu__menu-item:last-of-type {
		color: rgb( 220, 30, 30 );
	}
`;

const Title = styled.h4`
	margin: 0;
	font-size: 16px;
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	line-height: 2em;

	> * {
		&:not( :last-child ) {
			margin-right: 4px;
		}
	}
`;

const ActionItems = styled.div`
	display: flex;
	justify-content: center;
	align-items: center;

	.is-tertiary {
		&:focus {
			box-shadow: none;
		}
	}
`;

const SectionHeading = ( { isChangingDisplayOrder, onChangeDisplayOrder } ) => {
	const { isUpeEnabled } = useContext( UpeToggleContext );
	const upePaymentMethods = useGetAvailablePaymentMethodIds();
	const {
		setOrderedPaymentMethodIds,
		isSaving,
		saveOrderedPaymentMethodIds,
	} = useGetOrderedPaymentMethodIds();

	const [ isConfirmationModalOpen, toggleConfirmationModal ] = useToggle(
		false
	);

	const { refreshAccount } = useAccount();

	const onChangeDisplayOrderCancel = () => {
		onChangeDisplayOrder( false );
		setOrderedPaymentMethodIds( upePaymentMethods );
	};

	const onChangeDisplayOrderSave = async () => {
		await saveOrderedPaymentMethodIds();
		onChangeDisplayOrder( false );
	};

	return (
		<StyledHeader>
			<Title>
				<span>
					{ __( 'Payment methods', 'woocommerce-gateway-stripe' ) }
				</span>{ ' ' }
				<Pill>
					{ __( 'Early access', 'woocommerce-gateway-stripe' ) }
				</Pill>
			</Title>
			{ isConfirmationModalOpen && (
				<DisableUpeConfirmationModal
					onClose={ toggleConfirmationModal }
				/>
			) }
			<ActionItems>
				{ ! isChangingDisplayOrder ? (
					<>
						{ ! isUpeEnabled && (
							<Button
								variant="tertiary"
								onClick={ () => onChangeDisplayOrder( true ) }
							>
								{ __(
									'Change display order',
									'woocommerce-gateway-stripe'
								) }
							</Button>
						) }
						<DropdownMenu
							icon={ moreVertical }
							label={ __(
								'Payment methods menu',
								'woocommerce-gateway-stripe'
							) }
							controls={ [
								{
									title: __(
										'Refresh payment methods',
										'woocommerce-gateway-stripe'
									),
									onClick: refreshAccount,
								},
								{
									title: __(
										'Disable',
										'woocommerce-gateway-stripe'
									),
									onClick: toggleConfirmationModal,
								},
							] }
						/>
					</>
				) : (
					<>
						<Button
							variant="tertiary"
							disabled={ isSaving }
							onClick={ () => onChangeDisplayOrderCancel() }
						>
							{ __( 'Cancel', 'woocommerce-gateway-stripe' ) }
						</Button>
						<Button
							variant="secondary"
							disabled={ isSaving }
							onClick={ () => onChangeDisplayOrderSave() }
						>
							{ __(
								'Save display order',
								'woocommerce-gateway-stripe'
							) }
						</Button>
					</>
				) }
			</ActionItems>
		</StyledHeader>
	);
};

export default SectionHeading;
