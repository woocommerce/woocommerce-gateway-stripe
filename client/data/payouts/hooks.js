import { useEffect } from 'react';
import { STORE_NAME } from '../constants';
import { useDispatch, useSelect } from '@wordpress/data';

export const useBalance = () => {
	const { refreshBalance } = useDispatch( STORE_NAME );

	const balance = useSelect(
		( select ) => select( STORE_NAME ).getBalance(),
		[]
	);
	const error = useSelect(
		( select ) => select( STORE_NAME ).getBalanceError(),
		[]
	);

	const isLoading = useSelect( ( select ) => {
		const { hasFinishedResolution, isResolving, isLoadingBalance } =
			select( STORE_NAME );

		return (
			isLoadingBalance() ||
			isResolving( 'getBalance' ) ||
			! hasFinishedResolution( 'getBalance' )
		);
	}, [] );

	return { balance, isLoading, error, refresh: refreshBalance };
};

/**
 * Subscribe to payouts state. The resolver runs once on first render; when `args`
 * change (pagination cursor, status filter), we explicitly dispatch a refresh rather
 * than relying on per-argument resolution cache.
 *
 * @param {Object} args Query args forwarded to the refresh action.
 * @return {Object} Payout data, pagination flags, loading/error state, and a refresh action.
 */
export const usePayouts = ( args = {} ) => {
	const { refreshPayouts } = useDispatch( STORE_NAME );

	const payouts = useSelect(
		( select ) => select( STORE_NAME ).getPayouts(),
		[]
	);
	const hasMore = useSelect(
		( select ) => select( STORE_NAME ).getPayoutsHasMore(),
		[]
	);
	const error = useSelect(
		( select ) => select( STORE_NAME ).getPayoutsError(),
		[]
	);
	const isLoading = useSelect( ( select ) => {
		const { hasFinishedResolution, isResolving, isLoadingPayouts } =
			select( STORE_NAME );

		return (
			isLoadingPayouts() ||
			isResolving( 'getPayouts' ) ||
			! hasFinishedResolution( 'getPayouts' )
		);
	}, [] );

	const argsKey = JSON.stringify( args );
	useEffect( () => {
		refreshPayouts( args );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ argsKey ] );

	return { payouts, hasMore, isLoading, error, refresh: refreshPayouts };
};
