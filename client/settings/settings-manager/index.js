/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import SettingsLayout from '../settings-layout';
import SettingsTabPanel from '../settings-tab-panel';
import SaveSettingsSection from '../save-settings-section';

const SettingsManager = () => {
	return (
		<SettingsLayout>
			<SettingsTabPanel />
			<SaveSettingsSection />
		</SettingsLayout>
	);
};

export default SettingsManager;
