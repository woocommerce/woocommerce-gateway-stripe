import { useDispatch, useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useAccount = () => {
	const { refreshAccount } = useDispatch( STORE_NAME );

	const data = useSelect( ( select ) => {
		const { getAccountData } = select( STORE_NAME );

		return getAccountData();
	}, [] );

	const isLoading = useSelect( ( select ) => {
		const { hasFinishedResolution, isResolving } = select( STORE_NAME );

		return (
			isResolving( 'getAccountData' ) ||
			! hasFinishedResolution( 'getAccountData' )
		);
	}, [] );

	const isRefreshing = useSelect( ( select ) => {
		const { isRefreshingAccount } = select( STORE_NAME );

		return isRefreshingAccount();
	}, [] );

	return { data, isLoading, isRefreshing, refreshAccount };
};

export const useGetCapabilities = () => {
	return useSelect( ( select ) => {
		const { getAccountCapabilities } = select( STORE_NAME );

		return getAccountCapabilities();
	}, [] );
};
