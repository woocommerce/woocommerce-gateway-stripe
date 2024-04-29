import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import React, { useContext } from 'react';
import styled from '@emotion/styled';
import { Button, ExternalLink } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import PaymentMethodsMap from '../../payment-methods-map';
import UpeToggleContext from '../upe-toggle/context';
import ConfirmationModal from 'wcstripe/components/confirmation-modal';
import InlineNotice from 'wcstripe/components/inline-notice';
import AlertTitle from 'wcstripe/components/confirmation-modal/alert-title';
import { useEnabledPaymentMethodIds } from 'wcstripe/data';
import { useGetCapabilities } from 'wcstripe/data/account';

const DeactivatingPaymentMethodsList = styled.ul`
	min-height: 150px;

	> * {
		&:not( :last-child ) {
			margin-bottom: $grid-unit-10;
		}
	}
`;

const PaymentMethodListItemContent = styled.div`
	display: inline-flex;
	align-items: center;
	vertical-align: middle;
	flex-wrap: nowrap;

	> * {
		margin-right: 4px;
		line-height: 1em;

		&:last-child {
			margin-right: 0;
		}
	}
`;

const DisableUpeConfirmationModal = ( { onClose } ) => {
	const { status, setIsUpeEnabled } = useContext( UpeToggleContext );

	const { createErrorNotice, createSuccessNotice } = useDispatch(
		'core/notices'
	);

	const handleConfirmation = () => {
		const callback = async () => {
			try {
				await setIsUpeEnabled( false );
				createSuccessNotice(
					__(
						'🤔 What made you disable the new payments experience?',
						'woocommerce-gateway-stripe'
					),
					{
						actions: [
							{
								label: __(
									'Share feedback (1 min)',
									'woocommerce-gateway-stripe'
								),
								url:
									'https://woocommerce.survey.fm/woocommerce-stripe-upe-opt-out-survey',
							},
						],
					}
				);
				onClose();
			} catch ( err ) {
				createErrorNotice(
					__(
						'There was an error disabling the new payment methods.',
						'woocommerce-gateway-stripe'
					)
				);
			}
		};

		// creating a separate callback so that the UI isn't blocked by the async call.
		callback();
	};

	const [ enabledPaymentMethodIds ] = useEnabledPaymentMethodIds();
	const capabilities = useGetCapabilities();

	const upePaymentMethods = enabledPaymentMethodIds.filter( ( method ) => {
		return (
			method !== 'card' &&
			method !== 'link' &&
			capabilities.hasOwnProperty( `${ method }_payments` )
		);
	} );

	return (
		<>
			<ConfirmationModal
				title={
					<AlertTitle
						title={ __(
							'Disable the new payments experience',
							'woocommerce-gateway-stripe'
						) }
					/>
				}
				onRequestClose={ onClose }
				actions={
					<>
						<Button
							isSecondary
							disabled={ status === 'pending' }
							onClick={ onClose }
						>
							{ __( 'Cancel', 'woocommerce-gateway-stripe' ) }
						</Button>
						<Button
							isPrimary
							isDestructive
							isBusy={ status === 'pending' }
							disabled={ status === 'pending' }
							onClick={ handleConfirmation }
						>
							{ __( 'Disable', 'woocommerce-gateway-stripe' ) }
						</Button>
					</>
				}
			>
				<p>
					{ __(
						'Without the new payments experience, your customers will only be able to pay using credit card / debit card. You will not be able to add other sales-boosting payment methods anymore.',
						'woocommerce-gateway-stripe'
					) }
				</p>

				{ upePaymentMethods.length > 0 ? (
					<>
						<p>
							{ __(
								'Payment methods that require the new payments experience:',
								'woocommerce-gateway-stripe'
							) }
						</p>
						<DeactivatingPaymentMethodsList>
							{ upePaymentMethods.map( ( method ) => {
								const {
									Icon: MethodIcon,
									label,
								} = PaymentMethodsMap[ method ];

								return (
									<li key={ method }>
										<PaymentMethodListItemContent>
											<MethodIcon size="small" />
											<span>{ label }</span>
										</PaymentMethodListItemContent>
									</li>
								);
							} ) }
						</DeactivatingPaymentMethodsList>
					</>
				) : null }

				<InlineNotice status="info" isDismissible={ false }>
					{ interpolateComponents( {
						mixedString: __(
							'Need help? Visit {{ docsLink /}} or {{supportLink /}}.',
							'woocommerce-gateway-stripe'
						),
						components: {
							docsLink: (
								<ExternalLink href="https://woocommerce.com/document/stripe/">
									{ __(
										'Stripe plugin docs',
										'woocommerce-gateway-stripe'
									) }
								</ExternalLink>
							),
							supportLink: (
								<ExternalLink href="https://woocommerce.com/contact-us/">
									{ __(
										'contact support',
										'woocommerce-gateway-stripe'
									) }
								</ExternalLink>
							),
						},
					} ) }
				</InlineNotice>
			</ConfirmationModal>
		</>
	);
};

export default DisableUpeConfirmationModal;
