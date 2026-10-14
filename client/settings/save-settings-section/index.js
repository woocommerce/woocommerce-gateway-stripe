import React, { useState } from 'react';
import styled from '@emotion/styled';
import SettingsSection from '../settings-section';
import { useSettings } from '../../data';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const SaveSettingsSectionWrapper = styled( SettingsSection )`
	text-align: right;
`;

const SaveSettingsSection = ( { onSettingsSave, agenticSaveRef } ) => {
	const { saveSettings, isSaving, isLoading } = useSettings();
	const [ isSavingAgentic, setIsSavingAgentic ] = useState( false );

	const isAgenticTab = Boolean( agenticSaveRef );

	const onClickHandler = async () => {
		if ( isAgenticTab ) {
			if ( agenticSaveRef?.current?.save ) {
				setIsSavingAgentic( true );
				try {
					await agenticSaveRef.current.save();
				} finally {
					setIsSavingAgentic( false );
				}
			}
			if ( onSettingsSave ) {
				onSettingsSave();
			}
			return;
		}

		await saveSettings();
		if ( onSettingsSave ) {
			onSettingsSave();
		}
	};

	const isBusy = isAgenticTab ? isSavingAgentic : isSaving;
	const isDisabled = isAgenticTab ? isSavingAgentic : isSaving || isLoading;

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
