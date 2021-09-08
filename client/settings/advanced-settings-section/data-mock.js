/**
 * External dependencies
 */
import { useCallback, useState } from 'react';

export const useDebugLog = () => {
	const [ value, setValue ] = useState( false );
	const toggleValue = useCallback(
		() => setValue( ( oldValue ) => ! oldValue ),
		[ setValue ]
	);

	return [ value, toggleValue ];
};

export const useDevMode = () => false;
