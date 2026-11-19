/**
 * DOM readers for product pages on both templates: the classic
 * `.variations_form` and the blockified Add to Cart + Options block.
 *
 * The block's Interactivity API store is private. For extensions it renders
 * hidden form inputs kept in sync with its state (`variation_id`, one
 * `attribute_*` per attribute), so both templates are read through the same
 * form fields.
 */

const CLASSIC_FORM_SELECTOR = '.variations_form';
const BLOCKIFIED_FORM_SELECTOR = '.wc-block-add-to-cart-with-options';
const BLOCKIFIED_VARIATION_SELECTOR = `${ BLOCKIFIED_FORM_SELECTOR } [class*="variation-selector"]`;

/**
 * Wrapper of the blockified template's quantity input (simple and variable products).
 */
export const BLOCKIFIED_QUANTITY_SELECTOR = `${ BLOCKIFIED_FORM_SELECTOR } .quantity`;

/**
 * @return {HTMLFormElement|null} The product form the express checkout button acts on.
 */
const getProductForm = () =>
	document.querySelector(
		`form${ CLASSIC_FORM_SELECTOR }, form${ BLOCKIFIED_FORM_SELECTOR }`
	);

/**
 * @return {boolean} Whether the page renders variation markup (either template).
 */
export const hasVariationSelectionUi = () =>
	Boolean(
		document.querySelector(
			`.single_variation_wrap, ${ CLASSIC_FORM_SELECTOR }`
		)
	);

/**
 * @return {boolean} Whether the page renders the blockified variation selector.
 */
export const hasBlockifiedVariationSelector = () =>
	Boolean( document.querySelector( BLOCKIFIED_VARIATION_SELECTOR ) );

/**
 * Reads the selected attributes from what the product form would submit.
 *
 * @return {{count: number, chosenCount: number, data: Object<string, string>}}
 *         Number of attribute fields, how many have a value, and name-to-value map.
 */
export const getSelectedVariationAttributes = () => {
	const form = getProductForm();
	const data = {};

	if ( form ) {
		const submission = new FormData( form );
		form.querySelectorAll( '[name^="attribute_"]' ).forEach(
			( { name } ) => {
				data[ name ] = submission.get( name ) || '';
			}
		);
	}

	const values = Object.values( data );

	return {
		count: values.length,
		chosenCount: values.filter( ( value ) => value.length > 0 ).length,
		data,
	};
};

/**
 * @return {string|null} The selected variation ID, or null when the
 *                       selection is incomplete or matches no variation.
 */
export const getSelectedVariationId = () => {
	const variationId = document.querySelector(
		'input[name="variation_id"]'
	)?.value;
	if ( ! variationId || variationId === '0' ) {
		return null;
	}

	// The block keeps the previous id after a deselection (the classic
	// script clears it), so an id with an incomplete selection is stale.
	const { count, chosenCount } = getSelectedVariationAttributes();
	return chosenCount === count ? variationId : null;
};

/**
 * Watches the blockified variation selector for the shopper's gestures.
 *
 * The block fires no variation event, so gestures are the only signal. It
 * updates `variation_id` after the gesture: read the selection after a short
 * delay.
 *
 * @param {Function} onChange Called whenever the selection may have changed.
 * @return {{disconnect: Function}|null} Handle, or null without a blockified selector.
 */
export const observeBlockifiedVariationSelection = ( onChange ) => {
	// keyup: the chips select on keydown, which fires neither click nor change.
	const gestureEvents = [ 'click', 'change', 'keyup' ];
	// Outermost matches only: nested rows match too, and gestures bubble.
	const selectors = [
		...document.querySelectorAll( BLOCKIFIED_VARIATION_SELECTOR ),
	].filter(
		( element ) =>
			! element.parentElement.closest( BLOCKIFIED_VARIATION_SELECTOR )
	);
	if ( ! selectors.length ) {
		return null;
	}

	selectors.forEach( ( element ) =>
		gestureEvents.forEach( ( eventName ) =>
			element.addEventListener( eventName, onChange )
		)
	);

	return {
		disconnect: () => {
			selectors.forEach( ( element ) =>
				gestureEvents.forEach( ( eventName ) =>
					element.removeEventListener( eventName, onChange )
				)
			);
		},
	};
};
