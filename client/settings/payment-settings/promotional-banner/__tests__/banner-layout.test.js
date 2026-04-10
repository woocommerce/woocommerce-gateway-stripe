import { render } from '@testing-library/react';
import {
	BannerCard,
	BannerIllustration,
	BannerIllustrationWithOffset,
	ButtonsRow,
	ButtonsRowWithMargin,
	CardColumn,
	CardInner,
	CenteredColumnIllustration,
	DismissButton,
	MainCTALink,
	NewPill,
} from 'wcstripe/settings/payment-settings/promotional-banner/banner-layout';

describe( 'Promotional banner layout', () => {
	it( 'should render all styled elements', () => {
		const { getByTestId } = render(
			<div>
				<BannerCard data-testid="banner-card" />
				<BannerIllustration data-testid="banner-illustration" />
				<BannerIllustrationWithOffset data-testid="banner-illustration-with-offset" />
				<ButtonsRow data-testid="buttons-row" />
				<ButtonsRowWithMargin data-testid="buttons-row-with-margin" />
				<CardInner data-testid="card-inner">
					<CardColumn data-testid="card-column" />
					<CenteredColumnIllustration data-testid="centered-column-illustration" />
				</CardInner>
				<MainCTALink data-testid="main-cta-link" />
				<NewPill data-testid="new-pill" />
				<DismissButton data-testid="dismiss-button" />
			</div>
		);

		const bannerCard = getByTestId( 'banner-card' );
		const bannerIllustration = getByTestId( 'banner-illustration' );
		const bannerIllustrationWithOffset = getByTestId(
			'banner-illustration-with-offset'
		);
		const buttonsRow = getByTestId( 'buttons-row' );
		const buttonsRowWithMargin = getByTestId( 'buttons-row-with-margin' );
		const cardInner = getByTestId( 'card-inner' );
		const cardColumn = getByTestId( 'card-column' );
		const centeredColumnIllustration = getByTestId(
			'centered-column-illustration'
		);
		const mainCTALink = getByTestId( 'main-cta-link' );
		const newPill = getByTestId( 'new-pill' );
		const dismissButton = getByTestId( 'dismiss-button' );

		expect( bannerCard ).toBeInTheDocument();
		expect( bannerIllustration ).toBeInTheDocument();
		expect( bannerIllustrationWithOffset ).toBeInTheDocument();
		expect( buttonsRow ).toBeInTheDocument();
		expect( buttonsRowWithMargin ).toBeInTheDocument();
		expect( cardInner ).toBeInTheDocument();
		expect( cardColumn ).toBeInTheDocument();
		expect( centeredColumnIllustration ).toBeInTheDocument();
		expect( mainCTALink ).toBeInTheDocument();
		expect( newPill ).toBeInTheDocument();
		expect( dismissButton ).toBeInTheDocument();
	} );
} );
