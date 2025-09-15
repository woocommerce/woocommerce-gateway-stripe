const stub = () => {};

module.exports = {
	__esModule: true,
	default: jest.fn(),
	useSelect: jest.fn( () => {
		return {};
	} ),
	useDispatch: jest.fn( () => {
		return jest.fn();
	} ),
	select: jest.fn( ( storeName ) => {
		return {};
	} ),
	dispatch: jest.fn( ( storeName ) => {
		return {};
	} ),
	createSelector: jest.fn( ( ...args ) => {
		const resultFunc = args[ args.length - 1 ];
		return typeof resultFunc === 'function' ? resultFunc : stub;
	} ),
	combineReducers: jest.fn(
		( reducers ) =>
			( state = {} ) =>
				state
	),
	createReduxStore: jest.fn( ( name, options ) => ( {
		getState: stub,
		subscribe: stub,
		dispatch: stub,
	} ) ),
	register: jest.fn(),
	registerStore: jest.fn(),
};
