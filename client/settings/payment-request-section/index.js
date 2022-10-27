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
		//this handles the link payment method checkbox. If it's enable we should add link to the rest of the
		//enabled payment method.
		// If false - we should remove link payment method from the enabled payment methods
		if ( isEnabled ) {
			updateEnabledMethodIds( [
				...new Set( [ ...enabledMethodIds, 'link' ] ),
			] );
		} else {
			updateEnabledMethodIds( [
				...enabledMethodIds.filter( ( id ) => id !== 'link' ),
			] );
		}
	};

	const displayLinkPaymentMethod =
		enabledMethodIds.includes( 'card' ) &&
		availablePaymentMethodIds.includes( 'link' );
	const isStripeLinkEnabled = enabledMethodIds.includes( 'link' );

	const customizeAppearanceURL = addQueryArgs( window.location.href, {
		area: 'payment_requests',
	} );

	return (
		<Card className="express-checkouts">
			<CardBody size={ 0 }>
				<ul className="express-checkouts-list">
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
					{ displayLinkPaymentMethod && (
						<li className="express-checkout has-icon-border">
							<div className="express-checkout__checkbox loadable-checkbox label-hidden">
								<CheckboxControl
									label={ __(
										'Link by Stripe Input',
										'woocommerce-payments'
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
													'New payment experience (UPE) needs to be enabled for Link. ' +
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
														href="https://link.co/terms"
													/>
												),
												privacyPolicy: (
													<a
														target="_blank"
														rel="noreferrer"
														href="https://link.co/privacy"
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
													/* eslint-disable-next-line max-len */
													href="https://woocommerce.com/document/payments/woocommerce-payments-stripe-link/"
												/>
											),
										},
									} )
									/* eslint-enable jsx-a11y/anchor-has-content */
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
