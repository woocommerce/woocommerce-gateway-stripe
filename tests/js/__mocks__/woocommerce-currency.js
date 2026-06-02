// Mock for @woocommerce/currency, which is a webpack external (loaded from
// WooCommerce globals at runtime) and no longer installed as a devDependency.
const CurrencyFactory = jest.fn( () => ( {
	formatAmount: jest.fn(
		( amount ) => `$${ Number( amount ).toFixed( 2 ) }`
	),
	formatCurrency: jest.fn(
		( amount ) => `$${ Number( amount ).toFixed( 2 ) }`
	),
	formatDecimal: jest.fn( ( amount ) => Number( amount ) ),
	formatDecimalString: jest.fn( ( amount ) => Number( amount ).toFixed( 2 ) ),
	getPriceFormat: jest.fn( () => '%1$s%2$s' ),
	render: jest.fn( ( amount ) => `$${ Number( amount ).toFixed( 2 ) }` ),
} ) );

module.exports = CurrencyFactory;
module.exports.default = CurrencyFactory;
