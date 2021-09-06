/**
 * External dependencies
 */
import { useContext } from 'react';

/**
 * Internal dependencies
 */
import UpeToggleContext from './context';

const useIsUpeEnabled = () => {
	const { isUpeEnabled, setIsUpeEnabled } = useContext( UpeToggleContext );

	return [ isUpeEnabled, setIsUpeEnabled ];
};

export default useIsUpeEnabled;
