import React from 'react';
import Chip from 'wcstripe/components/chip';

const SectionStatus = ( { isEnabled, children } ) => {
	return <Chip text={ children } color={ isEnabled ? 'green' : 'yellow' } />;
};

export default SectionStatus;
