import React from 'react';
import { Icon } from '@wordpress/components';

import './style.scss';

const SectionStatusEnabled = ( { children } ) => {
	return (
		<span className="section-status__info--green">
			<Icon icon="yes-alt" />
			{ children }
		</span>
	);
};

const SectionStatusDisabled = ( { children } ) => {
	return (
		<span className="section-status__info--yellow">
			<Icon icon="warning" />
			{ children }
		</span>
	);
};

const SectionStatus = ( { isEnabled, children } ) => {
	return isEnabled ? (
		<SectionStatusEnabled>{ children }</SectionStatusEnabled>
	) : (
		<SectionStatusDisabled>{ children }</SectionStatusDisabled>
	);
};

export default SectionStatus;
