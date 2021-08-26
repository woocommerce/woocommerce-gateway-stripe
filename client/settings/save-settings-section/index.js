/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { styled } from '@linaria/react';

/**
 * Internal dependencies
 */
import SettingsSection from '../settings-section';

const SaveSettingsSectionWrapper = styled( SettingsSection )`
	text-align: right;
`;

const SaveSettingsSection = () => {
	return (
		<SaveSettingsSectionWrapper>
			<Button
				isPrimary
				onClick={ () => alert( 'Welcome to the settings screen.' ) }
			>
				{ __( 'Save changes', 'woocommerce-gateway-stripe' ) }
			</Button>
		</SaveSettingsSectionWrapper>
	);
};

export default SaveSettingsSection;
