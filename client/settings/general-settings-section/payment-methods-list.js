import { __ } from '@wordpress/i18n';
import React, { useContext, useState, useEffect } from 'react';
import styled from '@emotion/styled';
import classnames from 'classnames';
import { Button } from '@wordpress/components';
import { Reorder } from 'framer-motion';
import UpeToggleContext from '../upe-toggle/context';
import PaymentMethodsMap from '../../payment-methods-map';
import PaymentMethodDescription from './payment-method-description';
import CustomizePaymentMethod from './customize-payment-method';
import PaymentMethodCheckbox from './payment-method-checkbox';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useManualCapture,
} from 'wcstripe/data';
import { useGetCapabilities } from 'wcstripe/data/account';
import PaymentMethodFeesPill from 'wcstripe/components/payment-method-fees-pill';

const List = styled( Reorder.Group )`
	margin: 0;

	> li {
		margin: 0;
		padding: 16px 24px 14px 24px;

		@media ( min-width: 660px ) {
			padding: 24px 24px 24px 24px;
		}

		&:not( :last-child ) {
			box-shadow: inset 0 -1px 0 #e8eaeb;
		}

		&.expanded {
			box-shadow: none;
			padding-bottom: 0;
		}
	}

	> div {
		margin: 0;
		padding: 16px 24px 14px 24px;

		@media ( min-width: 660px ) {
			padding: 16px 24px 24px 24px;
		}

		&:not( :last-child ) {
			box-shadow: inset 0 -1px 0 #e8eaeb;
		}
	}
`;

const ListElement = styled( Reorder.Item )`
	display: flex;
	flex-wrap: nowrap;
	gap: 16px;

	@media ( min-width: 660px ) {
		align-items: center;
	}

	&.has-overlay {
		position: relative;

		&:after {
			content: '';
			position: absolute;
			// adds some spacing for the borders, so that they're not part of the opacity
			top: 1px;
			bottom: 1px;
			// ensures that the info icon isn't part of the opacity
			left: 55px;
			right: 0;
			background: white;
			opacity: 0.5;
			pointer-events: none;
		}
	}

	button {
		&.hide {
			visibility: hidden;
		}
	}
`;

const PaymentMethodWrapper = styled.div`
	display: flex;
	flex-direction: column;
	gap: 20px;

	@media ( min-width: 660px ) {
		flex-direction: row;
		flex-wrap: nowrap;
		align-items: center;
	}
`;

const StyledFees = styled( PaymentMethodFeesPill )`
	flex: 1 0 auto;
`;

const GeneralSettingsSection = ( { isChangingDisplayOrder } ) => {
	const { isUpeEnabled } = useContext( UpeToggleContext );
	const [ customizationStatus, setCustomizationStatus ] = useState( {} );
	const upePaymentMethods = useGetAvailablePaymentMethodIds();
	const capabilities = useGetCapabilities();
	const [ isManualCaptureEnabled ] = useManualCapture();
	const [ enabledPaymentMethodIds ] = useEnabledPaymentMethodIds();
	const [ paymentMethods, setPaymentMethods ] = useState( [] );

	useEffect( () => {
		// Hide payment methods that are not part of the account capabilities.
		const availablePaymentMethods = upePaymentMethods
			.filter( ( method ) =>
				capabilities.hasOwnProperty( `${ method }_payments` )
			)
			.filter( ( id ) => id !== 'link' );

		// Remove Sofort if it's not enabled. Hide from the new merchants and keep it for the old ones who are already using this gateway, until we remove it completely.
		// Stripe is deprecating Sofort https://support.stripe.com/questions/sofort-is-being-deprecated-as-a-standalone-payment-method.
		if (
			! enabledPaymentMethodIds.includes( 'sofort' ) &&
			availablePaymentMethods.includes( 'sofort' )
		) {
			availablePaymentMethods.splice(
				availablePaymentMethods.indexOf( 'sofort' )
			);
		}

		setPaymentMethods( availablePaymentMethods );
	}, [ capabilities, enabledPaymentMethodIds, upePaymentMethods ] );

	const onReorder = ( newOrder ) => {
		console.log( 'newOrder is', newOrder );
		setPaymentMethods( newOrder );
	};

	return (
		<List axis="y" values={ paymentMethods } onReorder={ onReorder }>
			{ paymentMethods.map( ( method ) => {
				const {
					Icon,
					label,
					description,
					allows_manual_capture: isAllowingManualCapture,
				} = PaymentMethodsMap[ method ];

				return (
					<div key={ method }>
						<ListElement
							key={ method }
							value={ method }
							className={ classnames( {
								'has-overlay':
									! isAllowingManualCapture &&
									isManualCaptureEnabled,
								expanded: customizationStatus[ method ],
							} ) }
						>
							<PaymentMethodCheckbox
								id={ method }
								label={ label }
								isAllowingManualCapture={
									isAllowingManualCapture
								}
							/>
							<PaymentMethodWrapper>
								<PaymentMethodDescription
									id={ method }
									Icon={ Icon }
									description={ description }
									label={ label }
								/>
								<StyledFees id={ method } />
							</PaymentMethodWrapper>
							{ ! isUpeEnabled &&
								method !== 'card' &&
								! customizationStatus[ method ] && (
									<Button
										variant="secondary"
										className={
											isChangingDisplayOrder ? 'hide' : ''
										}
										onClick={ () =>
											setCustomizationStatus( {
												...customizationStatus,
												[ method ]: true,
											} )
										}
									>
										{ __(
											'Customize',
											'woocommerce-gateway-stripe'
										) }
									</Button>
								) }
						</ListElement>
						{ ! isUpeEnabled && customizationStatus[ method ] && (
							<CustomizePaymentMethod
								method={ method }
								onClose={ () =>
									setCustomizationStatus( {
										...customizationStatus,
										[ method ]: false,
									} )
								}
							/>
						) }
					</div>
				);
			} ) }
		</List>
	);
};

export default GeneralSettingsSection;
