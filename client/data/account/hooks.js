import { useDispatch, useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

const EMPTY_OBJ = {};

export const useAccount = () => {
	const { refreshAccount } = useDispatch( STORE_NAME );

	const account = useSelect( ( select ) => {
		const { getAccount } = select( STORE_NAME );

		return getAccount();
	}, [] );

	const isLoading = useSelect( ( select ) => {
		const { hasFinishedResolution, isResolving } = select( STORE_NAME );

		return (
			isResolving( 'getAccount' ) ||
			! hasFinishedResolution( 'getAccount' )
		);
	}, [] );

	const isRefreshing = useSelect( ( select ) => {
		const { isRefreshingAccount } = select( STORE_NAME );

		return isRefreshingAccount();
	}, [] );

	return { account, isLoading, isRefreshing, refreshAccount };
};

export const useGetCapabilities = () => {
	return useSelect( ( select ) => {
		const { getAccount } = select( STORE_NAME );

		return getAccount().capabilities || EMPTY_OBJ;
	}, [] );
};
