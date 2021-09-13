/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import styled from '@emotion/styled';

/**
 * Internal dependencies
 */
import SettingsSection from '../settings-section';
import { useSettings } from '../../data';

const SaveSettingsSectionWrapper = styled( SettingsSection )`
	text-align: right;
`;

const SaveSettingsSection = () => {
	const { saveSettings, isSaving, isLoading } = useSettings();

	return (
		<SaveSettingsSectionWrapper>
			<Button
				isPrimary
				isBusy={ isSaving }
				disabled={ isSaving || isLoading }
				onClick={ saveSettings }
			>
				{ __( 'Save changes', 'woocommerce-gateway-stripe' ) }
			</Button>
		</SaveSettingsSectionWrapper>
	);
};

export default SaveSettingsSection;
