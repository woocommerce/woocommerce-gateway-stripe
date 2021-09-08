/**
 * External dependencies
 */
import React, { useContext } from 'react';
import styled from '@emotion/styled';
import { __ } from '@wordpress/i18n';
import { Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import CardsIcon from '../../payment-method-icons/cards';
import UpeOptInBanner from '../upe-opt-in-banner';
import UpeToggleContext from '../upe-toggle/context';

const GeneralSettingsSectionWrapper = styled.div`
	display: flex;
	flex-direction: column;
`;

const CardBodyWrapper = styled( CardBody )`
	display: flex;

	> * {
		margin-bottom: 0px;
	}
`;

const PaymentMethodText = styled.div`
	flex: 0 0 100%;

	@media ( min-width: 660px ) {
		flex: 1 1 auto;
		margin-left: 12px;
	}
`;

const PaymentMethodLabel = styled.div`
	color: $gray-900;
	display: inline-block;
	font-size: 14px;
	font-weight: 600;
	line-height: 20px;
	margin-bottom: 4px;
`;

const PaymentMethodDescription = styled.div`
	color: ##646970;
	font-size: 13px;
	line-height: 16px;
	margin-bottom: 14px;

	@media ( min-width: 660px ) {
		margin-bottom: 0px;
	}
`;

const UpeOptInBannerWrapper = styled.div`
	div:first-of-type {
		max-width: 100%;
	}
`;

const GeneralSettingsSection = () => {
	const { isUpeEnabled } = useContext( UpeToggleContext );

	return (
		<GeneralSettingsSectionWrapper>
			<Card>
				<CardBodyWrapper>
					<CardsIcon size="medium" />
					<PaymentMethodText>
						<PaymentMethodLabel>
							{ __(
								'Credit card / debit card',
								'woocommerce-gateway-stripe'
							) }
						</PaymentMethodLabel>
						<PaymentMethodDescription>
							{ __(
								'Let your customers pay with major credit and debit cards without leaving your store.',
								'woocommerce-gateway-stripe'
							) }
						</PaymentMethodDescription>
					</PaymentMethodText>
				</CardBodyWrapper>
			</Card>
			{ ! isUpeEnabled && (
				<UpeOptInBannerWrapper data-testid="opt-in-banner">
					<UpeOptInBanner />
				</UpeOptInBannerWrapper>
			) }
		</GeneralSettingsSectionWrapper>
	);
};

export default GeneralSettingsSection;
