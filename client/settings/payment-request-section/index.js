import { __ } from '@wordpress/i18n';
import React from 'react';
import styled from '@emotion/styled';
import {
	Button,
	Card,
	CardDivider,
	CheckboxControl,
} from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import interpolateComponents from 'interpolate-components';
import CardBody from '../card-body';
import {
	usePaymentRequestEnabledSettings,
	usePaymentRequestLocations,
} from '../../data';

const customizeAppearanceURL = addQueryArgs( window.location.href, {
	area: 'payment_requests',
} );

const AdditionalControlsWrapper = styled.div`
	position: relative;

	&::after {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		content: ' ';
		background: white;
		opacity: 0.5;

		${ ( { hasOverlay } ) => ( hasOverlay ? 'display: none;' : '' ) }
	}
`;

const PaymentRequestSection = () => {
	const [
		isPaymentRequestEnabled,
		updateIsPaymentRequestEnabled,
	] = usePaymentRequestEnabledSettings();
	const [
		paymentRequestLocations,
		updatePaymentRequestLocations,
	] = usePaymentRequestLocations();

	const makeLocationChangeHandler = ( location ) => ( isChecked ) => {
		if ( isChecked ) {
			updatePaymentRequestLocations( [
				...paymentRequestLocations,
				location,
			] );
		} else {
			updatePaymentRequestLocations(
				paymentRequestLocations.filter( ( name ) => name !== location )
			);
		}
	};

	return (
		<Card className="payment-request">
			<CardBody>
				<CheckboxControl
					checked={ isPaymentRequestEnabled }
					onChange={ updateIsPaymentRequestEnabled }
					label={ __(
						'Enable express checkouts',
						'woocommerce-gateway-stripe'
					) }
					/* eslint-disable jsx-a11y/anchor-has-content */
					help={ interpolateComponents( {
						mixedString: __(
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
					} ) }
					/* eslint-enable jsx-a11y/anchor-has-content */
				/>
				<AdditionalControlsWrapper
					hasOverlay={ isPaymentRequestEnabled }
				>
					<h4>
						{ __(
							'Show express checkouts on',
							'woocommerce-gateway-stripe'
						) }
					</h4>
					<ul>
						<li>
							<CheckboxControl
								disabled={ ! isPaymentRequestEnabled }
								checked={
									isPaymentRequestEnabled &&
									paymentRequestLocations.includes(
										'checkout'
									)
								}
								onChange={ makeLocationChangeHandler(
									'checkout'
								) }
								label={ __(
									'Checkout',
									'woocommerce-gateway-stripe'
								) }
							/>
						</li>
						<li>
							<CheckboxControl
								disabled={ ! isPaymentRequestEnabled }
								checked={
									isPaymentRequestEnabled &&
									paymentRequestLocations.includes(
										'product'
									)
								}
								onChange={ makeLocationChangeHandler(
									'product'
								) }
								label={ __(
									'Product page',
									'woocommerce-gateway-stripe'
								) }
							/>
						</li>
						<li>
							<CheckboxControl
								disabled={ ! isPaymentRequestEnabled }
								checked={
									isPaymentRequestEnabled &&
									paymentRequestLocations.includes( 'cart' )
								}
								onChange={ makeLocationChangeHandler( 'cart' ) }
								label={ __(
									'Cart',
									'woocommerce-gateway-stripe'
								) }
							/>
						</li>
					</ul>
				</AdditionalControlsWrapper>
			</CardBody>
			<CardDivider />
			<CardBody>
				<Button isSecondary href={ customizeAppearanceURL }>
					{ __(
						'Customize appearance',
						'woocommerce-gateway-stripe'
					) }
				</Button>
			</CardBody>
		</Card>
	);
};

export default PaymentRequestSection;
