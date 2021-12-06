import { __ } from '@wordpress/i18n';
import React, { useContext } from 'react';
import styled from '@emotion/styled';
import classNames from 'classnames';
import { Card, VisuallyHidden } from '@wordpress/components';
import LoadableSettingsSection from '../loadable-settings-section';
import SectionHeading from './section-heading';
import SectionFooter from './section-footer';
import PaymentMethodsList from './payment-methods-list';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';
import { useAccount } from 'wcstripe/data/account';
import './styles.scss';

const StyledCard = styled( Card )`
	margin-bottom: 12px;
`;

const AccountRefreshingOverlay = styled.div`
	position: relative;
	&.has-overlay {
		animation: loading-fade 1.6s ease-in-out infinite;

		&:after {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			content: ' ';
			background: white;
			opacity: 0.4;
		}
	}
`;

const GeneralSettingsSection = () => {
	const { isUpeEnabled } = useContext( UpeToggleContext );
	const { isRefreshing } = useAccount();

	return (
		<>
			<StyledCard>
				<LoadableSettingsSection numLines={ isUpeEnabled ? 30 : 7 }>
					{ isUpeEnabled && <SectionHeading /> }
					{ isRefreshing && (
						<VisuallyHidden>
							{ __(
								'Updating payment methods information, please wait.',
								'woocommerce-gateway-stripe'
							) }
						</VisuallyHidden>
					) }
					<AccountRefreshingOverlay
						className={ classNames( {
							'has-overlay': isRefreshing,
						} ) }
					>
						<PaymentMethodsList />
					</AccountRefreshingOverlay>
					{ isUpeEnabled && <SectionFooter /> }
				</LoadableSettingsSection>
			</StyledCard>
		</>
	);
};

export default GeneralSettingsSection;
