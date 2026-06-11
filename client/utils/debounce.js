const debounce = ( func, delay ) => {
	let timeoutId;

	return function ( ...args ) {
		clearTimeout( timeoutId );
		timeoutId = setTimeout( () => {
			func.apply( this, args );
		}, delay );
	};
};

export default debounce;
