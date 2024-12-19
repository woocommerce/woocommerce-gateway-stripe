import { __ } from '@wordpress/i18n';
import React, { useContext } from 'react';
import styled from '@emotion/styled';
import {
	Card,
	CheckboxControl,
	TextControl,
	ExternalLink,
} from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import CardBody from '../card-body';
import StatementPreviewsWrapper from './statement-previews-wrapper';
import StatementPreview from './statement-preview';
import ManualCaptureControl from './manual-capture-control';
import { useAccount } from 'wcstripe/data/account';
import {
	useSavedCards,
	useSeparateCardForm,
	useEnabledPaymentMethodIds,
	useIsShortAccountStatementEnabled,
} from 'wcstripe/data';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';
import { PAYMENT_METHOD_CASHAPP } from 'wcstripe/stripe-utils/constants';

const StatementDescriptorInputWrapper = styled.div`
	position: relative;

	.components-base-control__field {
		@media ( min-width: 783px ) {
			width: ${ ( props ) => ( props.isCashAppEnabled ? 30 : 50 ) }%;
		}

		.components-text-control__input {
			// to make room for the help text, so that the input's text and the help text don't overlap
			padding-right: 55px;
		}
	}
`;

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
	const [ enabledPaymentMethods ] = useEnabledPaymentMethodIds();

	const isCashAppEnabled = enabledPaymentMethods.includes(
		PAYMENT_METHOD_CASHAPP
	);

	const { isUpeEnabled } = useContext( UpeToggleContext );

	const translatedFullBankPreviewTitle = isShortAccountStatementEnabled
		? __( 'All Other Payment Methods', 'woocommerce-gateway-stripe' )
		: __( 'All Payment Methods', 'woocommerce-gateway-stripe' );

	const { data } = useAccount();
	const stripeAccountStatementDescriptor =
		data?.account?.settings?.payments?.statement_descriptor || '';

	const stripeAccountShortStatementDescriptor =
		data?.account?.settings?.card_payments?.statement_descriptor_prefix ||
		'';

	const stripeAccountCompanyName = data?.account?.company_name || '';

	// Stripe requires the short statement descriptor suffix to have at least 1 latin character.
	// To meet this requirement, we use the first character of the full statement descriptor.
	const shortStatementDescriptorSuffix = stripeAccountShortStatementDescriptor.charAt(
		0
	);

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
				<StatementDescriptorInputWrapper>
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
						disabled={ true } // This field is read only. It is set in the Stripe account.
					/>
				</StatementDescriptorInputWrapper>

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
					<StatementDescriptorInputWrapper>
						<TextControl
							help={ interpolateComponents( {
								mixedString: __(
									"We'll use the shortened descriptor in combination with the customer order number. You can change the shortened description in your {{settingsLink}}Stripe account settings{{/settingsLink}}.",
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
							disabled={ true } // This field is read only. It is set in the Stripe account.
						/>
					</StatementDescriptorInputWrapper>
				) }
				<StatementPreviewsWrapper withTreeColumns={ isCashAppEnabled }>
					{ isShortAccountStatementEnabled && (
						<StatementPreview
							icon="creditCard"
							title={ __(
								'Cards & Express Checkouts',
								'woocommerce-gateway-stripe'
							) }
							text={ `${ stripeAccountShortStatementDescriptor }* ${ shortStatementDescriptorSuffix } #123456` }
							className={ `shortened-bank-statement ${
								isCashAppEnabled ? 'with-tree-columns' : ''
							}` }
						/>
					) }
					{ isCashAppEnabled && (
						<StatementPreview
							icon="cashApp"
							title={ __(
								'Cash App Payments',
								'woocommerce-gateway-stripe'
							) }
							text={ `CashApp*${ stripeAccountCompanyName }` }
							className="full-bank-statement with-tree-columns"
						/>
					) }
					<StatementPreview
						icon="bank"
						title={ translatedFullBankPreviewTitle }
						text={
							stripeAccountStatementDescriptor ||
							stripeAccountShortStatementDescriptor
						}
						className={ `full-bank-statement ${
							isCashAppEnabled ? 'with-tree-columns' : ''
						}` }
					/>
				</StatementPreviewsWrapper>
			</CardBody>
		</Card>
	);
};

export default PaymentsAndTransactionsSection;
