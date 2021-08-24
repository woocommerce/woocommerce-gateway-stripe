/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import SettingsLayout from '../settings-layout';
import SaveSettingsSection from '../save-settings-section';
import SettingsTabPanel from '../settings-tab-panel';

const SettingsManager = () => {
	return (
		<SettingsLayout>
			<SettingsTabPanel />
			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default SettingsManager;
