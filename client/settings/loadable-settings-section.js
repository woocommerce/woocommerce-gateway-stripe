/** @format */
/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
// import { useSettings } from '../data';
import { LoadableBlock } from '../components/loadable';

//TODO: This should come from data
const useSettings = () => {
	return {
		isLoading: false
	}
};

const LoadableSettingsSection = ( { children, numLines } ) => {
	const { isLoading } = useSettings();

	return (
		<LoadableBlock isLoading={ isLoading } numLines={ numLines }>
			{ children }
		</LoadableBlock>
	);
};

export default LoadableSettingsSection;
