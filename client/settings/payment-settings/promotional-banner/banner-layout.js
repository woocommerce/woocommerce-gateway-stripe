import styled from '@emotion/styled';
import { Button, Card } from '@wordpress/components';
import Pill from 'wcstripe/components/pill';

export const BannerCard = styled( Card )`
	margin-bottom: 12px;
`;

export const BannerIllustration = styled.img`
	margin: 24px 0 0 24px;
`;

export const ButtonsRow = styled.p`
	margin: 0;
`;

export const CardInner = styled.div`
	display: flex;
	align-items: center;
	padding-bottom: 0;
	margin-bottom: 0;
	p {
		color: #757575;
	}
	@media ( max-width: 599px ) {
		display: block;
	}
`;

export const CardColumn = styled.div`
	flex: 1 auto;
`;

export const MainCTALink = styled( Button )`
	margin-right: 8px;
`;

export const NewPill = styled( Pill )`
	border-color: #674399;
	color: #674399;
	margin-bottom: 13px;
`;

export const DismissButton = styled( Button )`
	box-shadow: none !important;
	color: #757575 !important;
`;

export const BannerIllustrationWithOffset = styled( BannerIllustration )`
	@media ( min-width: 600px ) {
		margin: 0 0 -40px 24px;
	}
`;

export const ButtonsRowWithMargin = styled( ButtonsRow )`
	@media ( min-width: 600px ) {
		margin-bottom: 0.7em;
	}
`;

export const CenteredColumnIllustration = styled( CardColumn )`
	@media ( max-width: 599px ) {
		text-align: center;
	}
`;
