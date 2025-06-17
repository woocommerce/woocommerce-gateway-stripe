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
		// The 'submit' class is used by WC core to clear unsaved changes warnings.
		// See https://github.com/woocommerce/woocommerce/blob/fc7ffce309662758c0d3383de8cc8e8c6a57a167/plugins/woocommerce/client/legacy/js/admin/settings.js#L139
		<SaveSettingsSectionWrapper className="submit">
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
