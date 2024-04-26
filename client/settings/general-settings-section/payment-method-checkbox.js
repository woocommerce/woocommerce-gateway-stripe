import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';
import React, { useState, useContext } from 'react';
import styled from '@emotion/styled';
import { CheckboxControl, VisuallyHidden } from '@wordpress/components';
import { Icon, info } from '@wordpress/icons';
import UpeToggleContext from '../upe-toggle/context';
import RemoveMethodConfirmationModal from './remove-method-confirmation-modal';
import {
	useEnabledPaymentMethodIds,
	useManualCapture,
	useIsStripeEnabled,
} from 'wcstripe/data';
import Tooltip from 'wcstripe/components/tooltip';

const StyledCheckbox = styled( CheckboxControl )`
	.components-base-control__field {
		margin-bottom: 0;
	}
`;

const AlertIcon = styled( Icon )`
	fill: #f0b849;
`;

const IconWrapper = styled.span`
	margin-right: 8px;
	flex-shrink: 0;
`;

const StyledLink = styled.a`
	&,
	&:hover,
	&:visited {
		color: white;
	}
`;

const PaymentMethodCheckbox = ( {
	id,
	label,
	isAllowingManualCapture,
	isCurrencySupported,
	paymentMethodCurrencies,
} ) => {
	const [ isManualCaptureEnabled ] = useManualCapture();
	const [ isConfirmationModalOpen, setIsConfirmationModalOpen ] = useState(
		false
	);
	const [
		enabledPaymentMethods,
		setEnabledPaymentMethods,
	] = useEnabledPaymentMethodIds();
	const [ , setIsStripeEnabled ] = useIsStripeEnabled();
	const { isUpeEnabled } = useContext( UpeToggleContext );

	const handleCheckboxChange = ( hasBeenChecked ) => {
		if ( ! hasBeenChecked ) {
			setIsConfirmationModalOpen( true );
			return;
		}

		// In legacy mode (UPE disabled), Stripe refers to the card payment method.
		// So if the card payment method is enabled, Stripe should be enabled.
		if ( id === 'card' && ! isUpeEnabled ) {
			setIsStripeEnabled( true );
		}

		setEnabledPaymentMethods( [ ...enabledPaymentMethods, id ] );
	};

	const handleRemovalConfirmation = () => {
		setIsConfirmationModalOpen( false );
		setEnabledPaymentMethods(
			enabledPaymentMethods.filter( ( m ) => m !== id )
		);

		// In legacy mode (UPE disabled), Stripe refers to the card payment method.
		// So if the card payment method is disabled, Stripe should be disabled.
		if ( id === 'card' && ! isUpeEnabled ) {
			setIsStripeEnabled( false );
		}
	};

	if ( ! isCurrencySupported ) {
		return (
			<Tooltip
				content={ interpolateComponents( {
					mixedString: sprintf(
						/* translators: $1: a payment method name. %2: Currency(ies). */
						__(
							'%1$s requires store currency to be set to %2$s. {{currencySettingsLink}}Set currency{{/currencySettingsLink}}',
							'woocommerce-gateway-stripe'
						),
						label,
						paymentMethodCurrencies.join( ', ' )
					),
					components: {
						currencySettingsLink: (
							<StyledLink
								href="/wp-admin/admin.php?page=wc-settings&tab=general"
								target="_blank"
								rel="noreferrer"
								onClick={ ( ev ) => {
									// Stop propagation is necessary so it doesn't trigger the tooltip click event.
									ev.stopPropagation();
								} }
							/>
						),
					},
				} ) }
			>
				<IconWrapper>
					<AlertIcon icon={ info } />
				</IconWrapper>
			</Tooltip>
		);
	}

	return (
		<>
			{ isManualCaptureEnabled && ! isAllowingManualCapture ? (
				<Tooltip
					content={ sprintf(
						/* translators: %s: a payment method name. */
						__(
							'%s is not available to your customers when the "manual capture" setting is enabled.',
							'woocommerce-gateway-stripe'
						),
						label
					) }
				>
					{ /* a span element is added here to ensure the tooltip can get the correct content to position itself */ }
					<IconWrapper>
						<AlertIcon icon={ info } />
						<VisuallyHidden>
							{ sprintf(
								/* translators: %s: a payment method name. */
								__(
									'%s cannot be enabled at checkout. Click to expand.'
								),
								label
							) }
						</VisuallyHidden>
					</IconWrapper>
				</Tooltip>
			) : (
				<StyledCheckbox
					label={ <VisuallyHidden>{ label }</VisuallyHidden> }
					onChange={ handleCheckboxChange }
					checked={ enabledPaymentMethods.includes( id ) }
				/>
			) }
			{ isConfirmationModalOpen && (
				<RemoveMethodConfirmationModal
					method={ id }
					onClose={ () => setIsConfirmationModalOpen( false ) }
					onConfirm={ handleRemovalConfirmation }
				/>
			) }
		</>
	);
};

export default PaymentMethodCheckbox;
