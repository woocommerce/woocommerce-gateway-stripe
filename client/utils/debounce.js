/**
 * Debounces a function by delaying its execution until after a specified delay.
 *
 * @param {Function} callback The function to debounce.
 * @param {number}   delay    The delay in milliseconds
 * @return {Function} The debounced function.
 */
const debounce = ( callback, delay ) => {
	let timeoutId;

	return function ( ...args ) {
		clearTimeout( timeoutId );
		timeoutId = setTimeout( () => {
			callback.apply( this, args );
		}, delay );
	};
};

export default debounce;
