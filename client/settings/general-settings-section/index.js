/**
 * External dependencies
 */
import React, { useContext } from 'react';
import styled from '@emotion/styled';
import { Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import UpeOptInBanner from './upe-opt-in-banner';
import SectionHeading from './section-heading';
import SectionFooter from './section-footer';
import PaymentMethodsList from './payment-methods-list';
import UpeToggleContext from 'wcstripe/settings/upe-toggle/context';
import LoadableSettingsSection from '../loadable-settings-section';

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
