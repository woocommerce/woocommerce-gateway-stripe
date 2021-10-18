import { __ } from '@wordpress/i18n';
import React from 'react';
import { Card, CheckboxControl, TextControl } from '@wordpress/components';
import CardBody from '../card-body';
import TextLengthHelpInputWrapper from './text-length-help-input-wrapper';
import StatementPreviewsWrapper from './statement-previews-wrapper';
import StatementPreview from './statement-preview';
import ManualCaptureControl from './manual-capture-control';
import {
	useSavedCards,
	useSeparateCardForm,
	useAccountStatementDescriptor,
	useIsShortAccountStatementEnabled,
	useShortAccountStatementDescriptor,
	useGetSavingError,
} from 'wcstripe/data';
import InlineNotice from 'wcstripe/components/inline-notice';

const PaymentsAndTransactionsSection = () => {
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
	const statementDescriptorErrorMessage = useGetSavingError()?.data?.details
		?.statement_descriptor?.message;
	const shortStatementDescriptorErrorMessage = useGetSavingError()?.data
		?.details?.short_statement_descriptor?.message;

	const translatedFullBankPreviewTitle = isShortAccountStatementEnabled
		? __( 'All Other Payment Methods', 'woocommerce-gateway-stripe' )
		: __( 'All Payment Methods', 'woocommerce-gateway-stripe' );

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
				<ManualCaptureControl />
				<h4>
					{ __(
						'Customer bank statement',
						'woocommerce-gateway-stripe'
					) }
				</h4>
				{ statementDescriptorErrorMessage && (
					<InlineNotice status="error" isDismissible={ false }>
						<span
							dangerouslySetInnerHTML={ {
								__html: statementDescriptorErrorMessage,
							} }
						/>
					</InlineNotice>
				) }
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
					<>
						{ shortStatementDescriptorErrorMessage && (
							<InlineNotice
								status="error"
								isDismissible={ false }
							>
								<span
									dangerouslySetInnerHTML={ {
										__html: shortStatementDescriptorErrorMessage,
									} }
								/>
							</InlineNotice>
						) }
						<TextLengthHelpInputWrapper
							textLength={
								shortAccountStatementDescriptor.length
							}
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
					</>
				) }
				<StatementPreviewsWrapper>
					{ isShortAccountStatementEnabled && (
						<StatementPreview
							icon="creditCard"
							title={ __(
								'Cards & Express Checkouts',
								'woocommerce-gateway-stripe'
							) }
							text={ `${ shortAccountStatementDescriptor }* #123456` }
							className="shortened-bank-statement"
						/>
					) }
					<StatementPreview
						icon="bank"
						title={ translatedFullBankPreviewTitle }
						text={ accountStatementDescriptor }
						className="full-bank-statement"
					/>
				</StatementPreviewsWrapper>
			</CardBody>
		</Card>
	);
};

export default PaymentsAndTransactionsSection;
