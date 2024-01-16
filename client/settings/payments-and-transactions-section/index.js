import { __ } from '@wordpress/i18n';
import React, { useContext } from 'react';
import {
	Card,
	CheckboxControl,
	TextControl,
	ExternalLink,
} from '@wordpress/components';
import { Icon, help } from '@wordpress/icons';
import interpolateComponents from 'interpolate-components';
import CardBody from '../card-body';
import TextLengthHelpInputWrapper from './text-length-help-input-wrapper';
import StatementPreviewsWrapper from './statement-previews-wrapper';
import StatementPreview from './statement-preview';
import ManualCaptureControl from './manual-capture-control';
import { useAccount } from 'wcstripe/data/account';
import Tooltip from 'wcstripe/components/tooltip';
import {
	useSavedCards,
	useSeparateCardForm,
	useIsShortAccountStatementEnabled,
	useGetSavingError,
} from 'wcstripe/data';
import InlineNotice from 'wcstripe/components/inline-notice';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

const TooltipBankStatementHelp = () => (
	<Tooltip
		content={ __(
			'The bank statement must contain only Latin characters, be between 5 and 22 characters, and not contain any of the special characters: \' " * < >',
			'woocommerce-gateway-stripe'
		) }
	>
		<span>
			<Icon style={ { fill: '#949494' } } icon={ help } />
		</span>
	</Tooltip>
);

const PaymentsAndTransactionsSection = () => {
	const [ isSavedCardsEnabled, setIsSavedCardsEnabled ] = useSavedCards();
	const [
		isSeparateCardFormEnabled,
		setIsSeparateCardFormEnabled,
	] = useSeparateCardForm();
	const [
		isShortAccountStatementEnabled,
		setIsShortAccountStatementEnabled,
	] = useIsShortAccountStatementEnabled();

	const { isUpeEnabled } = useContext( UpeToggleContext );

	const statementDescriptorErrorMessage = useGetSavingError()?.data?.details
		?.statement_descriptor?.message;
	const shortStatementDescriptorErrorMessage = useGetSavingError()?.data
		?.details?.short_statement_descriptor?.message;

	const translatedFullBankPreviewTitle = isShortAccountStatementEnabled
		? __( 'All Other Payment Methods', 'woocommerce-gateway-stripe' )
		: __( 'All Payment Methods', 'woocommerce-gateway-stripe' );

	const { data } = useAccount();
	const stripeAccountStatementDescriptor =
		data?.account?.settings?.payments?.statement_descriptor || '';

	const stripeAccountShortStatementDescriptor =
		data?.account?.settings?.card_payments?.statement_descriptor_prefix ||
		'';

	return (
		<Card className="transactions-and-payouts">
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
				{ ! isUpeEnabled && (
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
				) }
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
					textLength={ stripeAccountStatementDescriptor.length }
					maxLength={ 22 }
					iconSlot={ <TooltipBankStatementHelp /> }
				>
					<TextControl
						help={ interpolateComponents( {
							mixedString: __(
								'You can change the description your customers will see on their bank statement in your {{settingsLink}}Stripe account settings{{/settingsLink}}. Set this to a recognizable name – e.g. the legal entity name or website address – to avoid potential disputes and chargebacks.',
								'woocommerce-gateway-stripe'
							),
							components: {
								settingsLink: (
									<ExternalLink href="https://dashboard.stripe.com/settings/public" />
								),
							},
						} ) }
						label={ __(
							'Full bank statement',
							'woocommerce-gateway-stripe'
						) }
						value={ stripeAccountStatementDescriptor }
						maxLength={ 22 }
						disabled={ true } // This field is read only. It is set in the Stripe account.
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
								stripeAccountShortStatementDescriptor.length
							}
							maxLength={ 10 }
						>
							<TextControl
								help={ interpolateComponents( {
									mixedString: __(
										"We'll use the shortened descriptor in combination with the customer order number. You can change the shortened description your {{settingsLink}}Stripe account settings{{/settingsLink}}.",
										'woocommerce-gateway-stripe'
									),
									components: {
										settingsLink: (
											<ExternalLink href="https://dashboard.stripe.com/settings/public" />
										),
									},
								} ) }
								label={ __(
									'Shortened customer bank statement',
									'woocommerce-gateway-stripe'
								) }
								value={ stripeAccountShortStatementDescriptor }
								maxLength={ 10 }
								disabled={ true } // This field is read only. It is set in the Stripe account.
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
							text={ `${ stripeAccountShortStatementDescriptor }* #123456` }
							className="shortened-bank-statement"
						/>
					) }
					<StatementPreview
						icon="bank"
						title={ translatedFullBankPreviewTitle }
						text={
							stripeAccountStatementDescriptor ||
							stripeAccountShortStatementDescriptor
						}
						className="full-bank-statement"
					/>
				</StatementPreviewsWrapper>
			</CardBody>
		</Card>
	);
};

export default PaymentsAndTransactionsSection;
