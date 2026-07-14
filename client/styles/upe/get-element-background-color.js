import { isTransparentColor } from './is-transparent-color';

/**
 * Get the background color for a provided element. Optionally check the parent element chain up to a specified
 * depth until you find a non-transparent background color. If no background color is found, return null.
 *
 * @param {HTMLElement} element             The element to check.
 * @param {Object}      options             The options for the background color search.
 * @param {boolean}     options.checkParent Whether to check the parent element. Default false.
 * @param {number}      options.maxDepth    The maximum depth to check. Will stop checking once this value is <= 1. Default 1.
 * @return {string|null} The background color, or null if no background color is found.
 */
export const getElementBackgroundColor = (
	element,
	{ checkParent = false, maxDepth = 1 } = {}
) => {
	const styles = window.getComputedStyle( element );
	if (
		styles.backgroundColor &&
		! isTransparentColor( styles.backgroundColor )
	) {
		return styles.backgroundColor;
	}

	if ( ! checkParent || maxDepth <= 1 ) {
		return null;
	}

	let currentElement = element;

	for ( let i = maxDepth; i > 1; i-- ) {
		currentElement = currentElement.parentElement;
		if ( ! currentElement ) {
			return null;
		}

		const currentStyles = window.getComputedStyle( currentElement );
		if (
			currentStyles.backgroundColor &&
			! isTransparentColor( currentStyles.backgroundColor )
		) {
			return currentStyles.backgroundColor;
		}
	}

	return null;
};
