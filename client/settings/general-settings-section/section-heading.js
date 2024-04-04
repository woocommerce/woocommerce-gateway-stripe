import { __ } from '@wordpress/i18n';
import React, { useContext } from 'react';
import styled from '@emotion/styled';
import { Button, CardHeader, DropdownMenu } from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';
import { useAccount } from 'wcstripe/data/account';
import {
	useGetAvailablePaymentMethodIds,
	useGetOrderedPaymentMethodIds,
} from 'wcstripe/data';
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
		orderedPaymentMethodIds,
		setOrderedPaymentMethodIds,
		isSaving,
		saveOrderedPaymentMethodIds,
	} = useGetOrderedPaymentMethodIds();

	const { refreshAccount } = useAccount();

	const onChangeDisplayOrderCancel = () => {
		onChangeDisplayOrder( false );
		setOrderedPaymentMethodIds( upePaymentMethods );
	};

	const onChangeDisplayOrderSave = async () => {
		await saveOrderedPaymentMethodIds();
		onChangeDisplayOrder( false, orderedPaymentMethodIds );
	};

	return (
		<StyledHeader>
			<Title>
				<span>
					{ __( 'Payment methods', 'woocommerce-gateway-stripe' ) }
				</span>{ ' ' }
			</Title>
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
						{ isUpeEnabled && (
							<DropdownMenu
								data-testid="upe-expandable-menu"
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
								] }
							/>
						) }
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
