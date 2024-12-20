import { __ } from '@wordpress/i18n';
import React from 'react';
import { Card, CheckboxControl } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import interpolateComponents from 'interpolate-components';
import PaymentRequestIcon from '../../payment-method-icons/payment-request';
import LinkIcon from '../../payment-method-icons/link';
import CardBody from '../card-body';
import {
	usePaymentRequestEnabledSettings,
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
} from '../../data';
import './styles.scss';
import {
	PAYMENT_METHOD_CARD,
	PAYMENT_METHOD_LINK,
} from 'wcstripe/stripe-utils/constants';

const PaymentRequestSection = () => {
	const [
		isPaymentRequestEnabled,
		updateIsPaymentRequestEnabled,
	] = usePaymentRequestEnabledSettings();

	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

	const [
		enabledMethodIds,
		updateEnabledMethodIds,
	] = useEnabledPaymentMethodIds();

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

	const displayExpressPaymentMethods = enabledMethodIds.includes(
		PAYMENT_METHOD_CARD
	);
	const displayLinkPaymentMethod =
		enabledMethodIds.includes( PAYMENT_METHOD_CARD ) &&
		availablePaymentMethodIds.includes( PAYMENT_METHOD_LINK );
	const isStripeLinkEnabled = enabledMethodIds.includes(
		PAYMENT_METHOD_LINK
	);

	const customizeAppearanceURL = addQueryArgs( window.location.href, {
		area: 'payment_requests',
	} );

	return (
		<Card className="express-checkouts">
			<CardBody size={ 0 }>
				<ul className="express-checkouts-list">
					{ ! displayExpressPaymentMethods &&
						! displayLinkPaymentMethod && (
							<li className="express-checkout">
								<div>
									{ __(
										'Credit card / debit card must be enabled as a payment method in order to use Express Checkout.',
										'woocommerce-gateway-stripe'
									) }
								</div>
							</li>
						) }
					{ displayExpressPaymentMethods && (
						<li className="express-checkout has-icon-border">
							<div className="express-checkout__checkbox">
								<CheckboxControl
									checked={ isPaymentRequestEnabled }
									onChange={ updateIsPaymentRequestEnabled }
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
					) }
					{ displayLinkPaymentMethod && (
						<li className="express-checkout has-icon-border">
							<div className="express-checkout__checkbox loadable-checkbox label-hidden">
								<CheckboxControl
									label={ __(
										'Link by Stripe Input',
										'woocommerce-gateway-stripe'
									) }
									checked={ isStripeLinkEnabled }
									onChange={ updateStripeLinkCheckout }
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
					) }
				</ul>
			</CardBody>
		</Card>
	);
};

export default PaymentRequestSection;
