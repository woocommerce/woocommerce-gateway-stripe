/** @format */

/**
 * Internal dependencies
 */
import { STORE_NAME } from './constants';
import { initStore } from './store';

initStore();

// eslint-disable-next-line @typescript-eslint/naming-convention
export const WC_STRIPE_STORE_NAME = STORE_NAME;

// We only ask for hooks when importing directly from 'wc_stripe/data'.
export * from './settings/hooks';
