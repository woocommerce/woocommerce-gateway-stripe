import React from 'react';
import { usePaymentGateway } from '../data';
import { LoadableBlock } from '../components/loadable';

const LoadablePaymentGatewaySection = ( { children, numLines } ) => {
	const { isLoading } = usePaymentGateway();

	return (
		<LoadableBlock isLoading={ isLoading } numLines={ numLines }>
			{ children }
		</LoadableBlock>
	);
};

export default LoadablePaymentGatewaySection;
