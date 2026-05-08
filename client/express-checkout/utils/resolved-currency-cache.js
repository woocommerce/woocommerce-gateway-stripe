let resolvedCurrency = null;

export const setResolvedCurrency = ( currency ) => {
	resolvedCurrency = currency || null;
};

export const getResolvedCurrency = ( fallback ) => resolvedCurrency || fallback;

export const __resetResolvedCurrencyForTests = () => {
	resolvedCurrency = null;
};
