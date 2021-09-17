import { __ } from '@wordpress/i18n';
import React from 'react';
import { Icon } from '@wordpress/components';

import './style.scss';

const SectionStatusEnabled = () => {
	return (
		<span className="section-status__info--green">
			<Icon icon="yes-alt" />
			{ __( 'Enabled', 'woocommerce-gateway-stripe' ) }
		</span>
	);
};

const SectionStatusDisabled = () => {
	return (
		<span className="section-status__info--yellow">
			<Icon icon="warning" />
			{ __( 'Disabled', 'woocommerce-gateway-stripe' ) }
		</span>
	);
};

const SectionStatus = ( { isEnabled } ) => {
	return isEnabled ? <SectionStatusEnabled /> : <SectionStatusDisabled />;
};

export default SectionStatus;
