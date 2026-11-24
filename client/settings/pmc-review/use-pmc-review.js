import { useCallback, useEffect, useRef, useState } from 'react';
import { useSettings } from '../../data';
import { useAccount } from '../../data/account';
import { NAMESPACE, STORE_NAME } from '../../data/constants';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const endpoint = `${ NAMESPACE }/account/pmc-review/methods`;

// The server includes the review in the page data while sync is off, so the table can
// replace the regular list on the first render instead of after a round of requests.
const getPreloadedReview = () => {
	const preloaded = window.wc_stripe_settings_params?.pmc_method_review;
	return preloaded && typeof preloaded === 'object' ? preloaded : null;
};

// Sync is off but both lists already match, so there is nothing to review and sync can resume.
const canAutoResume = ( review ) =>
	review?.differs === false &&
	Boolean( review.revision ) &&
	Array.isArray( review.methods );

const usePmcReview = ( { onResolved, hasUnsavedChanges = false } = {} ) => {
	const { settings, isLoading, isSaving } = useSettings();
	const { data, isLoading: isAccountLoading, isRefreshing } = useAccount();
	const hasAccount = Boolean( data?.account?.id );
	const { updateSettings } = useDispatch( STORE_NAME );
	const [ review, setReview ] = useState( getPreloadedReview );
	const skipNextFetch = useRef( review !== null );
	const [ error, setError ] = useState( '' );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ retry, setRetry ] = useState( 0 );
	// Read through a ref, so typing in other settings doesn't re-trigger the auto-resume effect.
	const unsavedChanges = useRef( hasUnsavedChanges );
	unsavedChanges.current = hasUnsavedChanges;
	// A failed auto-resume must not loop on the same revision; a manual refresh allows one more try.
	const autoResumedRevision = useRef( null );

	const refresh = useCallback( () => {
		autoResumedRevision.current = null;
		setRetry( ( count ) => count + 1 );
	}, [] );

	const save = useCallback(
		async ( enabled ) => {
			setIsBusy( true );
			try {
				const saved = await apiFetch( {
					path: endpoint,
					method: 'POST',
					data: { revision: review?.revision, enabled },
				} );
				updateSettings( saved );
				onResolved?.( saved );
				setReview( null );
				setError( '' );
			} catch ( e ) {
				setError( e.message );
			} finally {
				setIsBusy( false );
			}
		},
		[ review, updateSettings, onResolved ]
	);

	useEffect( () => {
		if ( isLoading || isSaving || ! canAutoResume( review ) ) {
			return;
		}
		// Other unsaved settings would be overwritten by the response, so wait for the next
		// comparison, which comes after they are saved.
		if (
			unsavedChanges.current ||
			autoResumedRevision.current === review.revision
		) {
			return;
		}
		autoResumedRevision.current = review.revision;
		save(
			review.methods
				.filter( ( method ) => method.stripe )
				.map( ( method ) => method.id )
		);
	}, [ review, save, isLoading, isSaving ] );

	useEffect( () => {
		if ( ! isLoading && settings.is_pmc_enabled ) {
			setReview( null );
			setError( '' );
			return;
		}
		if (
			isLoading ||
			isAccountLoading ||
			! hasAccount ||
			isSaving ||
			isRefreshing
		) {
			return;
		}
		if ( skipNextFetch.current ) {
			skipNextFetch.current = false;
			return;
		}
		let cancelled = false;
		apiFetch( { path: endpoint } )
			.then( ( result ) => {
				if ( ! cancelled ) {
					setReview( result );
					setError( '' );
				}
			} )
			.catch( ( e ) => {
				if ( ! cancelled ) {
					setError( e.message );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [
		isLoading,
		isAccountLoading,
		hasAccount,
		isSaving,
		isRefreshing,
		settings.is_pmc_enabled,
		settings.is_test_mode_enabled,
		retry,
	] );

	return { review, error, isBusy, refresh, save };
};

export default usePmcReview;
