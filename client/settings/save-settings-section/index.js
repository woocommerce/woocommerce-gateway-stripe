import { __ } from '@wordpress/i18n';
import React from 'react';
import { Button } from '@wordpress/components';
import styled from '@emotion/styled';
import SettingsSection from '../settings-section';
import { useSettings } from '../../data';

const SaveSettingsSectionWrapper = styled( SettingsSection )`
	text-align: right;
`;

const SaveSettingsSection = ( { onSettingsSave } ) => {
	const { saveSettings, isSaving, isLoading } = useSettings();

	const onClickHandler = async () => {
		await saveSettings();
		if ( onSettingsSave ) {
			onSettingsSave();
		}
	};

	return (
		<SaveSettingsSectionWrapper>
			<Button
				isPrimary
				isBusy={ isSaving }
				disabled={ isSaving || isLoading }
				onClick={ onClickHandler }
			>
				{ __( 'Save changes', 'woocommerce-gateway-stripe' ) }
			</Button>
		</SaveSettingsSectionWrapper>
	);
};

export default SaveSettingsSection;
