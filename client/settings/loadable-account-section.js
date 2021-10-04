import React from 'react';
import { LoadableBlock } from '../components/loadable';
import { useAccount } from 'wcstripe/data/account/hooks';
import { useAccountKeys } from 'wcstripe/data/account-keys/hooks';

const LoadableAccountSection = ( { children, numLines } ) => {
	const { isLoading: isAccountLoading } = useAccount();
	const { isLoading: areAccountKeysLoading } = useAccountKeys();

	return (
		<LoadableBlock
			isLoading={ areAccountKeysLoading || isAccountLoading }
			numLines={ numLines }
		>
			{ children }
		</LoadableBlock>
	);
};

export default LoadableAccountSection;
