import { STORE_NAME } from './constants';
import { initStore } from './store';

initStore();

export const WC_STRIPE_STORE_NAME = STORE_NAME;

// We only ask for hooks when importing directly from 'wcstripe/data'.
export * from './settings/hooks';
export * from './payment-gateway/hooks';
