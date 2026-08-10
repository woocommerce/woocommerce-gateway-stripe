let elementCurrency = null;

export const setElementCurrency = ( currency ) => {
	elementCurrency = currency || null;
};

export const getElementCurrency = () => elementCurrency;

export const __resetElementCurrencyForTests = () => {
	elementCurrency = null;
};
