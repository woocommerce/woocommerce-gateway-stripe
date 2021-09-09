/**
 * External dependencies
 */
import React, { useContext, useState } from 'react';
import styled from '@emotion/styled';
import { __ } from '@wordpress/i18n';
import { CardHeader, DropdownMenu } from '@wordpress/components';
import { moreVertical } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import Pill from 'wcstripe/components/pill';
import UpeToggleContext from '../upe-toggle/context';
import DisableUpeConfirmationModal from './disable-upe-confirmation-modal';

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

const SectionHeading = () => {
	const { isUpeEnabled } = useContext( UpeToggleContext );

	const [ isConfirmationModalOpen, setIsConfirmationModalOpen ] = useState(
		false
	);

	if ( ! isUpeEnabled ) {
		return null;
	}

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
					onClose={ () => setIsConfirmationModalOpen( false ) }
				/>
			) }
			<DropdownMenu
				icon={ moreVertical }
				label={ __(
					'Disable the new Payment Experience',
					'woocommerce-gateway-stripe'
				) }
				controls={ [
					{
						title: __( 'Disable', 'woocommerce-gateway-stripe' ),
						onClick: () => setIsConfirmationModalOpen( true ),
					},
				] }
			/>
		</StyledHeader>
	);
};

export default SectionHeading;
