import { React } from 'react';
import styled from '@emotion/styled';
import interpolateComponents from '@automattic/interpolate-components';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import CardBody from 'wcstripe/settings/card-body';
import illustration from 'wcstripe/settings/payment-settings/promotional-banner/illustrations/oc.svg';
import {
	CardColumn,
	CardInner,
	DismissButton,
	MainCTALink,
	BannerIllustrationWithOffset,
	ButtonsRowWithMargin,
	CenteredColumnIllustration,
} from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';
import { dismissNotice } from 'wcstripe/utils';

const TitleBNPL = styled.h4`
	margin-top: 0.6em !important;
	font-weight: 500;
`;

export const OCPromotionBanner = ( {
	setShowPromotionalBanner,
	setIsOCEnabled,
} ) => {
	const { createErrorNotice, createSuccessNotice } =
		useDispatch( 'core/notices' );

	const handleBannerDismiss = () => {
		dismissNotice( 'wc_stripe_show_oc_promotion_banner', () => {
			setShowPromotionalBanner( false );
		} );
	};

	const handleButtonClick = () => {
		const callback = async () => {
			try {
				await setIsOCEnabled( true );

				createSuccessNotice(
					__(
						'Optimized Checkout suite enabled',
						'woocommerce-gateway-stripe'
					)
				);

				window.location.reload();
			} catch ( err ) {
				createErrorNotice(
					__(
						'There was an error. Please reload the page and try again.',
						'woocommerce-gateway-stripe'
					)
				);
			}
		};

		// creating a separate callback so that the UI isn't blocked by the async call.
		callback();
	};

	return (
		<CardBody>
			<CardInner>
				<CardColumn>
					<TitleBNPL>
						{ __(
							"Increase conversion with Stripe's Optimized Checkout Suite",
							'woocommerce-gateway-stripe'
						) }
					</TitleBNPL>
					<p>
						{ interpolateComponents( {
							mixedString: __(
								"Optimize your checkout experience for more sales by dynamically displaying the most relevant payment methods you've enabled for each customer. {{docLink}}Learn more{{/docLink}} about Stripe's Optimized Checkout Suite.",
								'woocommerce-gateway-stripe'
							),
							components: {
								docLink: (
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a
										target="_blank"
										rel="noreferrer"
										href="https://woocommerce.com/document/stripe/admin-experience/optimized-checkout-suite/"
									/>
								),
							},
						} ) }
					</p>
				</CardColumn>
				<CenteredColumnIllustration>
					<BannerIllustrationWithOffset
						src={ illustration }
						alt={ __(
							'Try the Optimized Checkout Suite',
							'woocommerce-gateway-stripe'
						) }
					/>
				</CenteredColumnIllustration>
			</CardInner>
			<ButtonsRowWithMargin>
				<MainCTALink variant="secondary" onClick={ handleButtonClick }>
					{ __( 'Activate now', 'woocommerce-gateway-stripe' ) }
				</MainCTALink>
				<DismissButton
					variant="secondary"
					onClick={ handleBannerDismiss }
					data-testid="dismiss"
				>
					{ __( 'Dismiss', 'woocommerce-gateway-stripe' ) }
				</DismissButton>
			</ButtonsRowWithMargin>
		</CardBody>
	);
};
