import React, { useState } from 'react';
import styled from '@emotion/styled';
import SettingsSection from '../settings-section';
import { useSettings } from '../../data';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const SaveSettingsSectionWrapper = styled( SettingsSection )`
	text-align: right;
`;

/**
 * Renders the save settings button section.
 *
 * @param {Object}   props                  Component props.
 * @param {Function} [props.onSettingsSave] Optional callback invoked after standard store saveSettings completes.
 * @param {Function} [props.onSave]         Optional custom save closure. When provided, SaveSettingsSection
 *                                          invokes this custom save handler and manages its own saving state,
 *                                          bypassing the global settings store saveSettings and onSettingsSave.
 *
 * @return {JSX.Element} The save settings button section.
 */
const SaveSettingsSection = ( { onSettingsSave, onSave } ) => {
	const { saveSettings, isSaving, isLoading } = useSettings();
	const [ isCustomSaving, setIsCustomSaving ] = useState( false );

	const onClickHandler = async () => {
		if ( onSave ) {
			setIsCustomSaving( true );
			try {
				await onSave();
			} finally {
				setIsCustomSaving( false );
			}
			return;
		}

		if ( saveSettings ) {
			await saveSettings();
		}
		if ( onSettingsSave ) {
			onSettingsSave();
		}
	};

	const isBusy = onSave ? isCustomSaving : isSaving;
	const isDisabled = onSave ? isCustomSaving : isSaving || isLoading;

	return (
		// The 'submit' class is used by WC core to clear unsaved changes warnings.
		// See https://github.com/woocommerce/woocommerce/blob/fc7ffce309662758c0d3383de8cc8e8c6a57a167/plugins/woocommerce/client/legacy/js/admin/settings.js#L139
		<SaveSettingsSectionWrapper className="submit">
			<Button
				isPrimary
				isBusy={ isBusy }
				disabled={ isDisabled }
				onClick={ onClickHandler }
			>
				{ __( 'Save changes', 'woocommerce-gateway-stripe' ) }
			</Button>
		</SaveSettingsSectionWrapper>
	);
};

export default SaveSettingsSection;
