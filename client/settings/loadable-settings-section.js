import React from 'react';
import { useSettings } from '../data';
import { LoadableBlock } from '../components/loadable';

const LoadableSettingsSection = ( { children, numLines } ) => {
	const { isLoading } = useSettings();

	return (
		<LoadableBlock isLoading={ isLoading } numLines={ numLines }>
			{ children }
		</LoadableBlock>
	);
};

export default LoadableSettingsSection;
