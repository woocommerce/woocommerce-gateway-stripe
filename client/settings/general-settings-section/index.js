import React, { useContext } from 'react';
import styled from '@emotion/styled';
import { Card } from '@wordpress/components';
import LoadableSettingsSection from '../loadable-settings-section';
import UpeOptInBanner from './upe-opt-in-banner';
import SectionHeading from './section-heading';
import SectionFooter from './section-footer';
import PaymentMethodsList from './payment-methods-list';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';

const StyledCard = styled( Card )`
	margin-bottom: 12px;
`;

const GeneralSettingsSection = () => {
	const { isUpeEnabled } = useContext( UpeToggleContext );

	return (
		<>
			<StyledCard>
				<LoadableSettingsSection numLines={ isUpeEnabled ? 30 : 7 }>
					{ isUpeEnabled && <SectionHeading /> }
					<PaymentMethodsList />
					{ isUpeEnabled && <SectionFooter /> }
				</LoadableSettingsSection>
			</StyledCard>
			<UpeOptInBanner />
		</>
	);
};

export default GeneralSettingsSection;
