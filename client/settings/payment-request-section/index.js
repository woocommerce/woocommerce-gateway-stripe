/* global wc_stripe_settings_params */

import React from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import PaymentRequestIcon from '../../payment-method-icons/payment-request';
import LinkIcon from '../../payment-method-icons/link';
import CardBody from '../card-body';
import {
	useExpressCheckoutEnabledSettings,
	useAmazonPayEnabledSettings,
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
} from '../../data';
import './styles.scss';
import AmazonPayIcon from '../../payment-method-icons/amazon-pay';
import PaymentMethodMissingCurrencyPill from '../../components/payment-method-missing-currency-pill';
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import {
	PAYMENT_METHOD_LINK,
	PAYMENT_METHOD_AMAZON_PAY,
	PAYMENT_METHOD_APPLE_PAY_GOOGLE_PAY,
	PAYMENT_METHOD_UNAVAILABLE_REASONS,
} from 'wcstripe/stripe-utils/constants';
import PaymentMethodUnavailableDueTaxSetupPill from 'wcstripe/components/payment-method-unavailable-due-tax-setup-pill';
import usePaymentMethodUnavailableReason from 'wcstripe/utils/use-payment-method-unavailable-reason';
import PaymentMethodRequiresCardMethodPill from 'wcstripe/components/payment-method-requires-card-method-pill';

const PaymentRequestSection = () => {
	const [ isExpressCheckoutEnabled, updateIsExpressCheckoutEnabled ] =
		useExpressCheckoutEnabledSettings();

	const [ isAmazonPayEnabled, updateIsAmazonPayEnabled ] =
		useAmazonPayEnabledSettings();

	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

	const [ enabledMethodIds, updateEnabledMethodIds ] =
		useEnabledPaymentMethodIds();

	const amazonPayUnavailableReason = usePaymentMethodUnavailableReason(
		PAYMENT_METHOD_AMAZON_PAY
	);

	const showUnavailableDueTaxSetupPillForAmazonPay =
		amazonPayUnavailableReason ===
		PAYMENT_METHOD_UNAVAILABLE_REASONS.TAX_BASED_ON_BILLING_ADDRESS;

	const showMissingCurrencyPillForAmazonPay =
		amazonPayUnavailableReason ===
		PAYMENT_METHOD_UNAVAILABLE_REASONS.UNSUPPORTED_CURRENCY;

	const applePayGooglePayUnavailableReason =
		usePaymentMethodUnavailableReason(
			PAYMENT_METHOD_APPLE_PAY_GOOGLE_PAY
		);

	const showRequiresCardMethodPillForApplePayGooglePay =
		applePayGooglePayUnavailableReason ===
		PAYMENT_METHOD_UNAVAILABLE_REASONS.REQUIRES_CARD_METHOD;

	const linkUnavailableReason =
		usePaymentMethodUnavailableReason( PAYMENT_METHOD_LINK );

	const showRequiresCardMethodPillForLink =
		linkUnavailableReason ===
		PAYMENT_METHOD_UNAVAILABLE_REASONS.REQUIRES_CARD_METHOD;

	const updateStripeLinkCheckout = ( isEnabled ) => {
		// Add/remove Stripe Link from the list of enabled payment methods.
		if ( isEnabled ) {
			updateEnabledMethodIds( [
				...enabledMethodIds,
				PAYMENT_METHOD_LINK,
			] );
		} else {
			updateEnabledMethodIds( [
				...enabledMethodIds.filter(
					( id ) => id !== PAYMENT_METHOD_LINK
				),
			] );
		}
	};

	const isStripeLinkEnabled =
		enabledMethodIds.includes( PAYMENT_METHOD_LINK );

	const customizeAppearanceURL = addQueryArgs( window.location.href, {
		area: 'payment_requests',
	} );
	const customizeAmazonPayAppearanceURL = addQueryArgs(
		window.location.href,
		{
			area: 'amazon_pay',
		}
	);

	const isAmazonPayAvailable =
		wc_stripe_settings_params.is_amazon_pay_available && // eslint-disable-line camelcase
		availablePaymentMethodIds.includes( PAYMENT_METHOD_AMAZON_PAY );

	const isAmazonPayDisabled =
		amazonPayUnavailableReason !== null &&
		! enabledMethodIds.includes( PAYMENT_METHOD_AMAZON_PAY );

	const isApplePayGooglePayDisabled =
		applePayGooglePayUnavailableReason !== null &&
		! isExpressCheckoutEnabled;

	const isLinkDisabled =
		linkUnavailableReason !== null && ! isStripeLinkEnabled;

	return (
		<Card className="express-checkouts">
			<CardBody size={ 0 }>
				<ul className="express-checkouts-list">
					<li className="express-checkout has-icon-border">
						<div className="express-checkout__checkbox">
							<CheckboxControl
								label={ __(
									'Apple Pay / Google Pay Input',
									'woocommerce-gateway-stripe'
								) }
								checked={ isExpressCheckoutEnabled }
								onChange={ updateIsExpressCheckoutEnabled }
								disabled={ isApplePayGooglePayDisabled }
							/>
						</div>
						<div className="express-checkout__icon">
							<PaymentRequestIcon size="medium" />
						</div>
						<div className="express-checkout__label-container">
							<div className="express-checkout__label">
								{ __(
									'Apple Pay / Google Pay',
									'woocommerce-gateway-stripe'
								) }
								{ showRequiresCardMethodPillForApplePayGooglePay && (
									<PaymentMethodRequiresCardMethodPill
										id={
											PAYMENT_METHOD_APPLE_PAY_GOOGLE_PAY
										}
										label={ __(
											'Apple Pay / Google Pay',
											'woocommerce-gateway-stripe'
										) }
									/>
								) }
							</div>
							<div className="express-checkout__description">
								{
									/* eslint-disable jsx-a11y/anchor-has-content */
									interpolateComponents( {
										mixedString: __(
											'Boost sales by offering a fast, simple, and secure checkout experience.' +
												'By enabling this feature, you agree to {{stripeLink}}Stripe{{/stripeLink}}, ' +
												"{{appleLink}}Apple{{/appleLink}}, and {{googleLink}}Google{{/googleLink}}'s terms of use.",
											'woocommerce-gateway-stripe'
										),
										components: {
											stripeLink: (
												<a
													target="_blank"
													rel="noreferrer"
													href="https://stripe.com/apple-pay/legal"
												/>
											),
											appleLink: (
												<a
													target="_blank"
													rel="noreferrer"
													href="https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/"
												/>
											),
											googleLink: (
												<a
													target="_blank"
													rel="noreferrer"
													href="https://androidpay.developers.google.com/terms/sellertos"
												/>
											),
										},
									} )
									/* eslint-enable jsx-a11y/anchor-has-content */
								}
							</div>
						</div>
						<div className="express-checkout__link">
							<a href={ customizeAppearanceURL }>
								{ __(
									'Customize',
									'woocommerce-gateway-stripe'
								) }
							</a>
						</div>
					</li>
					<li className="express-checkout has-icon-border">
						<div className="express-checkout__checkbox loadable-checkbox label-hidden">
							<CheckboxControl
								label={ __(
									'Link by Stripe Input',
									'woocommerce-gateway-stripe'
								) }
								checked={ isStripeLinkEnabled }
								onChange={ updateStripeLinkCheckout }
								disabled={ isLinkDisabled }
							/>
						</div>
						<div className="express-checkout__icon">
							<LinkIcon size="medium" />
						</div>
						<div className="express-checkout__label-container">
							<div className="express-checkout__label">
								{ __(
									'Link by Stripe',
									'woocommerce-gateway-stripe'
								) }
								{ showRequiresCardMethodPillForLink && (
									<PaymentMethodRequiresCardMethodPill
										id={ PAYMENT_METHOD_LINK }
										label={ __(
											'Link by Stripe',
											'woocommerce-gateway-stripe'
										) }
									/>
								) }
							</div>
							<div className="express-checkout__description">
								{
									/* eslint-disable jsx-a11y/anchor-has-content */
									interpolateComponents( {
										mixedString: __(
											'Link autofills your customers’ payment and shipping details to ' +
												'deliver an easy and seamless checkout experience. ' +
												'New checkout experience needs to be enabled for Link. ' +
												'By enabling this feature, you agree to the ' +
												'{{stripeLinkTerms}}Link by Stripe terms{{/stripeLinkTerms}}, ' +
												'and {{privacyPolicy}}Privacy Policy{{/privacyPolicy}}.',
											'woocommerce-gateway-stripe'
										),
										components: {
											stripeLinkTerms: (
												<a
													target="_blank"
													rel="noreferrer"
													href="https://link.com/terms"
												/>
											),
											privacyPolicy: (
												<a
													target="_blank"
													rel="noreferrer"
													href="https://link.com/privacy"
												/>
											),
										},
									} )
									/* eslint-enable jsx-a11y/anchor-has-content */
								}
							</div>
						</div>
						<div className="express-checkout__link">
							{
								/* eslint-disable jsx-a11y/anchor-has-content */
								interpolateComponents( {
									mixedString: __(
										'{{linkDocs}}Read more{{/linkDocs}}',
										'woocommerce-gateway-stripe'
									),
									components: {
										linkDocs: (
											<a
												target="_blank"
												rel="noreferrer"
												href="https://woocommerce.com/document/stripe/customer-experience/express-checkouts/#link-by-stripe"
											/>
										),
									},
								} )
							}
						</div>
					</li>
					{ isAmazonPayAvailable && (
						<li className="express-checkout has-icon-border">
							<div className="express-checkout__checkbox">
								<CheckboxControl
									label={ __(
										'Amazon Pay Input',
										'woocommerce-gateway-stripe'
									) }
									checked={ isAmazonPayEnabled }
									onChange={ updateIsAmazonPayEnabled }
									disabled={ isAmazonPayDisabled }
								/>
							</div>
							<div className="express-checkout__icon">
								<AmazonPayIcon size="medium" />
							</div>
							<div className="express-checkout__label-container">
								<div className="express-checkout__label">
									{ __(
										'Amazon Pay',
										'woocommerce-gateway-stripe'
									) }
									{ showMissingCurrencyPillForAmazonPay && (
										<PaymentMethodMissingCurrencyPill
											id={ PAYMENT_METHOD_AMAZON_PAY }
											label={ __(
												'Amazon Pay',
												'woocommerce-gateway-stripe'
											) }
										/>
									) }
									{ showUnavailableDueTaxSetupPillForAmazonPay && (
										<PaymentMethodUnavailableDueTaxSetupPill
											id={ PAYMENT_METHOD_AMAZON_PAY }
											label={ __(
												'Amazon Pay',
												'woocommerce-gateway-stripe'
											) }
										/>
									) }
								</div>
								<div className="express-checkout__description">
									{
										/* eslint-disable jsx-a11y/anchor-has-content */
										interpolateComponents( {
											mixedString: __(
												'Enhance sales by providing a quick, straightforward, and secure checkout experience. ' +
													'By activating this feature, you accept the terms of use ' +
													'for {{stripeLink}}Stripe{{/stripeLink}} and {{amazonLink}}Amazon{{/amazonLink}}.',
												'woocommerce-gateway-stripe'
											),
											components: {
												stripeLink: (
													<a
														target="_blank"
														rel="noreferrer"
														href="https://stripe.com/legal/ssa"
													/>
												),
												amazonLink: (
													<a
														target="_blank"
														rel="noreferrer"
														href="https://stripe.com/legal/amazon-pay"
													/>
												),
											},
										} )
										/* eslint-enable jsx-a11y/anchor-has-content */
									}
								</div>
							</div>
							<div className="express-checkout__amazon">
								<a href={ customizeAmazonPayAppearanceURL }>
									{ __(
										'Customize',
										'woocommerce-gateway-stripe'
									) }
								</a>
							</div>
						</li>
					) }
				</ul>
			</CardBody>
		</Card>
	);
};

export default PaymentRequestSection;
