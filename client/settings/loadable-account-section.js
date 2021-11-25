import React from 'react';
import { LoadableBlock } from '../components/loadable';
import { useAccount } from 'wcstripe/data/account/hooks';
import { useAccountKeys } from 'wcstripe/data/account-keys/hooks';

const LoadableAccountSection = ( {
	children,
	numLines,
	keepContent = false,
} ) => {
	const { isLoading: isAccountLoading } = useAccount();
	const { isLoading: areAccountKeysLoading } = useAccountKeys();
	let isLoading = areAccountKeysLoading || isAccountLoading;

	if ( keepContent ) {
		isLoading = false;
	}

	return (
		<LoadableBlock isLoading={ isLoading } numLines={ numLines }>
			{ children }
		</LoadableBlock>
	);
};

export default LoadableAccountSection;
