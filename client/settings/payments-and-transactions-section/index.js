import { __ } from '@wordpress/i18n';
import React from 'react';
import { Card, CheckboxControl, TextControl } from '@wordpress/components';
import CardBody from '../card-body';
import TextLengthHelpInputWrapper from './text-length-help-input-wrapper';
import {
	useManualCapture,
	useSavedCards,
	useSeparateCardForm,
	useAccountStatementDescriptor,
	useIsShortAccountStatementEnabled,
	useShortAccountStatementDescriptor,
} from 'wcstripe/data';

const PaymentsAndTransactionsSection = () => {
	const [
		isManualCaptureEnabled,
		setIsManualCaptureEnabled,
	] = useManualCapture();
	const [ isSavedCardsEnabled, setIsSavedCardsEnabled ] = useSavedCards();
	const [
		isSeparateCardFormEnabled,
		setIsSeparateCardFormEnabled,
	] = useSeparateCardForm();
	const [
		accountStatementDescriptor,
		setAccountStatementDescriptor,
	] = useAccountStatementDescriptor();
	const [
		isShortAccountStatementEnabled,
		setIsShortAccountStatementEnabled,
	] = useIsShortAccountStatementEnabled();
	const [
		shortAccountStatementDescriptor,
		setShortAccountStatementDescriptor,
	] = useShortAccountStatementDescriptor();

	return (
		<Card className="transactions-and-deposits">
			<CardBody>
				<h4>
					{ __( 'Payments settings', 'woocommerce-gateway-stripe' ) }
				</h4>
				<CheckboxControl
					checked={ isSavedCardsEnabled }
					onChange={ setIsSavedCardsEnabled }
					label={ __(
						'Enable payments via saved cards',
						'woocommerce-gateway-stripe'
					) }
					help={ __(
						'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Stripe servers, not on your store.',
						'woocommerce-gateway-stripe'
					) }
				/>
				<CheckboxControl
					checked={ isSeparateCardFormEnabled }
					onChange={ setIsSeparateCardFormEnabled }
					label={ __(
						'Enable separate credit card form',
						'woocommerce-gateway-stripe'
					) }
					help={ __(
						'If enabled, the credit card form will display separate credit card number field, expiry date field and CVC field.',
						'woocommerce-gateway-stripe'
					) }
				/>
				<h4>
					{ __(
						'Transaction preferences',
						'woocommerce-gateway-stripe'
					) }
				</h4>
				<CheckboxControl
					checked={ isManualCaptureEnabled }
					onChange={ setIsManualCaptureEnabled }
					label={ __(
						'Issue an authorization on checkout, and capture later',
						'woocommerce-gateway-stripe'
					) }
					help={ __(
						'Charge must be captured on the order details screen within 7 days of authorization, otherwise the authorization and order will be canceled.',
						'woocommerce-gateway-stripe'
					) }
				/>
				<h4>
					{ __(
						'Customer bank statement',
						'woocommerce-gateway-stripe'
					) }
				</h4>
				<TextLengthHelpInputWrapper
					textLength={ accountStatementDescriptor.length }
					maxLength={ 22 }
				>
					<TextControl
						help={ __(
							'Enter the name your customers will see on their transactions. Use a recognizable name – e.g. the legal entity name or website address–to avoid potential disputes and chargebacks.',
							'woocommerce-gateway-stripe'
						) }
						label={ __(
							'Full bank statement',
							'woocommerce-gateway-stripe'
						) }
						value={ accountStatementDescriptor }
						onChange={ setAccountStatementDescriptor }
						maxLength={ 22 }
					/>
				</TextLengthHelpInputWrapper>
				<CheckboxControl
					checked={ isShortAccountStatementEnabled }
					onChange={ setIsShortAccountStatementEnabled }
					label={ __(
						'Add customer order number to the bank statement',
						'woocommerce-gateway-stripe'
					) }
					help={ __(
						"When enabled, we'll include the order number for card and express checkout transactions.",
						'woocommerce-gateway-stripe'
					) }
				/>
				{ isShortAccountStatementEnabled && (
					<TextLengthHelpInputWrapper
						textLength={ shortAccountStatementDescriptor.length }
						maxLength={ 10 }
					>
						<TextControl
							help={ __(
								"We'll use the short version in combination with the customer order number.",
								'woocommerce-gateway-stripe'
							) }
							label={ __(
								'Shortened customer bank statement',
								'woocommerce-gateway-stripe'
							) }
							value={ shortAccountStatementDescriptor }
							onChange={ setShortAccountStatementDescriptor }
							maxLength={ 10 }
						/>
					</TextLengthHelpInputWrapper>
				) }
			</CardBody>
		</Card>
	);
};

export default PaymentsAndTransactionsSection;
