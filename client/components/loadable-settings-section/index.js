import React from 'react';
// Will use once data persistence set up.
// import { useSettings } from '../data';
import { LoadableBlock } from '../loadable';

const LoadableSettingsSection = ( { children, numLines } ) => {
	//const { isLoading } = useSettings();
	const isLoading = false;

	return (
		<LoadableBlock isLoading={ isLoading } numLines={ numLines }>
			{ children }
		</LoadableBlock>
	);
};

export default LoadableSettingsSection;
