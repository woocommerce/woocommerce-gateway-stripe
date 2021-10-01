import { __ } from '@wordpress/i18n';
import React, { useState, useCallback } from 'react';
import { Icon, chevronDown, chevronUp } from '@wordpress/icons';
import { Card, Button } from '@wordpress/components';
import SettingsSection from '../settings-section';
import CardBody from '../card-body';
import DebugMode from './debug-mode';

const useToggle = ( initialValue = false ) => {
	const [ value, setValue ] = useState( initialValue );
	const toggleValue = useCallback(
		() => setValue( ( oldValue ) => ! oldValue ),
		[ setValue ]
	);

	return [ value, toggleValue ];
};

const AdvancedSettings = () => {
	const [ isSectionExpanded, handleToggleSectionExpansion ] = useToggle(
		false
	);

	return (
		<>
			<SettingsSection>
				<Button onClick={ handleToggleSectionExpansion } isTertiary>
					{ __( 'Advanced settings', 'woocommerce-gateway-stripe' ) }
					<Icon
						icon={ isSectionExpanded ? chevronUp : chevronDown }
					/>
				</Button>
			</SettingsSection>
			{ isSectionExpanded && (
				<SettingsSection>
					<Card>
						<CardBody>
							<DebugMode />
						</CardBody>
					</Card>
				</SettingsSection>
			) }
		</>
	);
};

export default AdvancedSettings;
