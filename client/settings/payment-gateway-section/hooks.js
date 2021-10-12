import { getGateway } from './helpers';
import * as hooks from 'wcstripe/data';

export const useEnabledGateway = () => {
	const gateway = getGateway();
	return hooks[ `useIsStripe${ gateway }Enabled` ]();
};

export const useGatewayName = () => {
	const gateway = getGateway();
	return hooks[ `useStripe${ gateway }Name` ]();
};

export const useGatewayDescription = () => {
	const gateway = getGateway();
	return hooks[ `useStripe${ gateway }Description` ]();
};
