import React, { useContext } from 'react';
import styled from '@emotion/styled';
import classnames from 'classnames';
import UpeToggleContext from '../upe-toggle/context';
import PaymentMethodsMap from '../../payment-methods-map';
import PaymentMethodDescription from './payment-method-description';
import PaymentMethodCheckbox from './payment-method-checkbox';
import {
	useGetAvailablePaymentMethodIds,
	useManualCapture,
} from 'wcstripe/data';
import { useGetCapabilities } from 'wcstripe/data/account';
import PaymentMethodFeesPill from 'wcstripe/components/payment-method-fees-pill';

const List = styled.ul`
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
	}
`;

const ListElement = styled.li`
	display: flex;
	flex-wrap: nowrap;

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

const GeneralSettingsSection = () => {
	const { isUpeEnabled } = useContext( UpeToggleContext );
	const upePaymentMethods = useGetAvailablePaymentMethodIds();
	const capabilities = useGetCapabilities();
	const [ isManualCaptureEnabled ] = useManualCapture();

	// Hide payment methods that are not part of the account capabilities.
	const availablePaymentMethods = upePaymentMethods
		.filter( ( method ) =>
			capabilities.hasOwnProperty( `${ method }_payments` )
		)
		.filter( ( id ) => id !== 'link' )
		.filter( ( id ) => id !== 'google_pay' );

	return (
		<List>
			{ availablePaymentMethods.map( ( method ) => {
				const {
					Icon,
					label,
					description,
					allows_manual_capture: isAllowingManualCapture,
				} = PaymentMethodsMap[ method ];

				return (
					<ListElement
						key={ method }
						className={ classnames( {
							'has-overlay':
								! isAllowingManualCapture &&
								isManualCaptureEnabled,
						} ) }
					>
						<PaymentMethodCheckbox
							id={ method }
							label={ label }
							isAllowingManualCapture={ isAllowingManualCapture }
						/>
						<PaymentMethodWrapper>
							<PaymentMethodDescription
								id={ method }
								Icon={ Icon }
								description={ description }
								label={ label }
							/>
							{ isUpeEnabled && <StyledFees id={ method } /> }
						</PaymentMethodWrapper>
					</ListElement>
				);
			} ) }
		</List>
	);
};

export default GeneralSettingsSection;
