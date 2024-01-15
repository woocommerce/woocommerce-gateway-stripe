import React from 'react';

import './style.scss';

const SectionStatusEnabled = ( { children } ) => {
	return (
		<span className="section-status__info section-status__info--green">
			{ children }
		</span>
	);
};

const SectionStatusDisabled = ( { children } ) => {
	return (
		<span className="section-status__info section-status__info--yellow">
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
