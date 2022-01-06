import { useEffect, useCallback, useRef } from '@wordpress/element';

/**
 * Hook for displaying a confirmation message before navigate.
 *
 * Usage:
 * - const callback = useConfirmNavigation( true );
 *   useEffect( callback , [ callback, otherDependency ] );
 *
 * @param {boolean} displayPrompt Whether we should prompt the message or not
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

		// eslint-disable-next-line @wordpress/no-global-event-listener
		window.addEventListener( 'beforeunload', handler );

		return () => {
			// eslint-disable-next-line @wordpress/no-global-event-listener
			window.removeEventListener( 'beforeunload', handler );
		};
	}, [] );
};

export default useConfirmNavigation;
