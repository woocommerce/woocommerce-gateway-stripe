import { __ } from '@wordpress/i18n';
import React, { useEffect } from 'react';
import { Icon, chevronDown, chevronUp } from '@wordpress/icons';
import { Card, Button } from '@wordpress/components';
import { getQuery } from '@woocommerce/navigation';
import SettingsSection from '../settings-section';
import CardBody from '../card-body';
import DebugMode from './debug-mode';
import ExperimentalFeatures from './experimental-features';
import LoadableSettingsSection from 'wcstripe/settings/loadable-settings-section';
import useToggle from 'wcstripe/hooks/use-toggle';

const AdvancedSettings = () => {
	const [ isSectionExpanded, toggleIsSectionExpanded ] = useToggle( false );

	useEffect( () => {
		const { highlight } = getQuery();
		if ( highlight === 'enable-upe' ) {
			toggleIsSectionExpanded( true );
		}
	}, [ toggleIsSectionExpanded ] );

	return (
		<>
			<SettingsSection>
				<Button onClick={ toggleIsSectionExpanded } isTertiary>
					{ __( 'Advanced settings', 'woocommerce-gateway-stripe' ) }
					<Icon
						icon={ isSectionExpanded ? chevronUp : chevronDown }
					/>
				</Button>
			</SettingsSection>
			{ isSectionExpanded && (
				<SettingsSection>
					<LoadableSettingsSection numLines={ 10 }>
						<Card>
							<CardBody>
								<DebugMode />
								<ExperimentalFeatures />
							</CardBody>
						</Card>
					</LoadableSettingsSection>
				</SettingsSection>
			) }
		</>
	);
};

export default AdvancedSettings;
