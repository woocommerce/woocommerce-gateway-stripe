import { useEffect, useCallback, useRef } from '@wordpress/element';

/**
 * Hook for displaying an optional confirmation message.
 *
 * Usage:
 * - const callback = useConfirmNavigation( true );
 *   useEffect( callback , [ callback, otherDependency ] );
 *
 * @param {boolean} displayPrompt Wether we should prompt the message or not
 * @return {Function} The callback to execute
 */
const useConfirmNavigation = ( displayPrompt ) => {
	const savedDisplayPrompt = useRef();

	useEffect( () => {
		savedDisplayPrompt.current = displayPrompt;
	} );

	return useCallback( () => {
		const doDisplayPrompt = savedDisplayPrompt.current;

		if ( ! doDisplayPrompt ) {
			return;
		}

		const handler = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', handler );

		return () => {
			window.removeEventListener( 'beforeunload', handler );
		};
	}, [] );
};

export default useConfirmNavigation;
