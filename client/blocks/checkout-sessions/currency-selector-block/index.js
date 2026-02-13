import { CurrencySelectorElement } from '@stripe/react-stripe-js/checkout';
import currencySelectorMetadata from './block.json';

export const currencySelectorBlock = {
	metadata: currencySelectorMetadata,
	// component: () => <CurrencySelectorElement />,
	component: () => <div>Test</div>,
};
