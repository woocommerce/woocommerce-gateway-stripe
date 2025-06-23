import { render } from '@testing-library/react';
import {
	BannerCard,
	BannerIllustration,
	ButtonsRow,
	CardColumn,
	CardInner,
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
				<ButtonsRow data-testid="buttons-row" />
				<CardInner data-testid="card-inner">
					<CardColumn data-testid="card-column" />
				</CardInner>
				<MainCTALink data-testid="main-cta-link" />
				<NewPill data-testid="new-pill" />
				<DismissButton data-testid="dismiss-button" />
			</div>
		);

		const bannerCard = getByTestId( 'banner-card' );
		const bannerIllustration = getByTestId( 'banner-illustration' );
		const buttonsRow = getByTestId( 'buttons-row' );
		const cardInner = getByTestId( 'card-inner' );
		const cardColumn = getByTestId( 'card-column' );
		const mainCTALink = getByTestId( 'main-cta-link' );
		const newPill = getByTestId( 'new-pill' );
		const dismissButton = getByTestId( 'dismiss-button' );

		expect( bannerCard ).toBeInTheDocument();
		expect( bannerIllustration ).toBeInTheDocument();
		expect( buttonsRow ).toBeInTheDocument();
		expect( cardInner ).toBeInTheDocument();
		expect( cardColumn ).toBeInTheDocument();
		expect( mainCTALink ).toBeInTheDocument();
		expect( newPill ).toBeInTheDocument();
		expect( dismissButton ).toBeInTheDocument();
	} );
} );
