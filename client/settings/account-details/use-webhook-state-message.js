import { useCallback, useEffect, useRef, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { useAccount } from 'wcstripe/data/account';
import { NAMESPACE } from 'wcstripe/data/constants';

const useWebhookStateMessage = () => {
	const { data } = useAccount();
	const dataStatusMessage = data.webhook_status_message;
	const isMakingRequest = useRef( false );
	const [ message, setMessage ] = useState( dataStatusMessage );
	const [ requestStatus, setRequestStatus ] = useState( 'idle' );

	useEffect( () => {
		// it's already making a request, no need to update
		if ( isMakingRequest.current === true ) {
			return;
		}

		if ( ! dataStatusMessage ) {
			return;
		}

		setMessage( dataStatusMessage );
		setRequestStatus( 'fulfilled' );
	}, [ dataStatusMessage ] );

	const refreshMessage = useCallback( () => {
		const callback = async () => {
			// trying to prevent multiple calls at a time
			if ( isMakingRequest.current === true ) {
				return;
			}

			isMakingRequest.current = true;
			setRequestStatus( 'pending' );

			try {
				const result = await apiFetch( {
					path: `${ NAMESPACE }/account/webhook-status-message`,
				} );
				setMessage( result );
				setRequestStatus( 'fulfilled' );
			} catch ( e ) {
				setRequestStatus( 'rejected' );
			}

			isMakingRequest.current = false;
		};

		// the callback uses async/await
		// using a separate `const` ensures that the main UI thread isn't acting "blocked", preventing other interactions.
		callback();
	}, [] );

	return { message, requestStatus, refreshMessage };
};

export default useWebhookStateMessage;
