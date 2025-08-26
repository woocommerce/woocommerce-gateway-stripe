import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { React } from 'react';
import styled from '@emotion/styled';
import interpolateComponents from 'interpolate-components';
import apiFetch from '@wordpress/api-fetch';
import CardBody from 'wcstripe/settings/card-body';
import illustration from 'wcstripe/settings/payment-settings/promotional-banner/illustrations/oc.svg';
import {
	BannerIllustration,
	ButtonsRow,
	CardColumn,
	CardInner,
	DismissButton,
	MainCTALink,
} from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';

const BannerIllustrationBNPL = styled( BannerIllustration )`
	@media ( min-width: 600px ) {
		margin: 0 0 -40px 24px;
	}
`;

const ButtonsRowBNPL = styled( ButtonsRow )`
	@media ( min-width: 600px ) {
		margin-bottom: 0.7em;
	}
`;

const ColumnIllustration = styled( CardColumn )`
	@media ( max-width: 599px ) {
		text-align: center;
	}
`;

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
		apiFetch( {
			path: '/wc/v3/wc_stripe/settings/notice',
			method: 'POST',
			data: { wc_stripe_show_oc_promotion_banner: 'no' },
		} ).finally( () => {
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
						{ __( '', 'woocommerce-gateway-stripe' ) }
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
				<ColumnIllustration>
					<BannerIllustrationBNPL
						src={ illustration }
						alt={ __(
							'Try the Optimized Checkout Suite',
							'woocommerce-gateway-stripe'
						) }
					/>
				</ColumnIllustration>
			</CardInner>
			<ButtonsRowBNPL>
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
			</ButtonsRowBNPL>
		</CardBody>
	);
};
