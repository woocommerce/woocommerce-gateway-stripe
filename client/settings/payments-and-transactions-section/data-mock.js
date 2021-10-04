import { useCallback, useState } from 'react';

// TODO: this is here just for testing purposes while we work on the backend data
const makeToggleHook = ( initialValue = false ) => () => {
	const [ value, setValue ] = useState( initialValue );
	const toggleValue = useCallback(
		() => setValue( ( oldValue ) => ! oldValue ),
		[ setValue ]
	);

	return [ value, toggleValue ];
};

export const useManualCapture = makeToggleHook( false );

export const useSavedCards = makeToggleHook( true );

export const useSeparateCardForm = makeToggleHook( false );

export const useShortAccountStatement = makeToggleHook( false );

export const useAccountStatementDescriptor = () =>
	useState( 'WOOTESTING, LTD' );

export const useShortAccountStatementDescriptor = () =>
	useState( 'WOOTESTING' );
