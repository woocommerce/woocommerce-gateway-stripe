import { useEffect, useCallback, useRef } from '@wordpress/element';
import { getHistory } from '@woocommerce/navigation';

/**
 * Hook for displaying an optional confirmation message.
 *
 * Usage:
 * - const callback = useConfirmNavigation( () => 'Are you sure you want to leave?' );
 *   useEffect( callback , [ callback, otherDependency ] );
 *
 * @param {Function} getMessage returns confirmation message string if one should appear
 * @return {Function} The callback to execute
 */
const useConfirmNavigation = ( getMessage ) => {
	const savedCallback = useRef();

	useEffect( () => {
		savedCallback.current = getMessage;
	} );

	return useCallback( () => {
		const message = savedCallback.current();
		if ( ! message ) {
			return;
		}

		const handler = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', handler );
		const unblock = getHistory().block( message );

		return () => {
			window.removeEventListener( 'beforeunload', handler );
			unblock();
		};
	}, [] );
};

export default useConfirmNavigation;
