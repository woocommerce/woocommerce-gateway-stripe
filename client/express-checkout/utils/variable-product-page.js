/**
 * DOM readers for variable product pages, covering both product templates:
 * the classic `.variations_form` markup and the blockified Add to Cart +
 * Options block.
 *
 * The block exposes no JavaScript API (its Interactivity API store is
 * private). What WooCommerce provides for extensions instead is a set of
 * hidden form inputs it keeps in sync with its state, explicitly "to be used
 * by extensions or Express Payment methods": the `.single_variation_wrap`
 * compat markup with the bound `variation_id` input
 * (`AddToCartWithOptions::render()`) and one hidden `attribute_*` input per
 * attribute (`VariationSelectorAttribute::render_attribute_row()`). The
 * classic template renders the equivalent fields natively, so both templates
 * are read through the same form fields.
 */

/**
 * The product form the express checkout button acts on: the classic
 * `.variations_form` or the blockified Add to Cart + Options block.
 *
 * @return {HTMLFormElement|null} The product form, or null when the page has none.
 */
const getProductForm = () =>
	document.querySelector(
		'form.variations_form, form.wc-block-add-to-cart-with-options'
	);

/**
 * Whether the page renders a variable product's variation UI (either template).
 *
 * @return {boolean} True when variation markup is present.
 */
export const hasVariationSelectionUi = () =>
	Boolean(
		document.querySelector( '.single_variation_wrap, .variations_form' )
	);

/**
 * Reads the currently selected variation attributes.
 *
 * Reads what the product form would submit: both templates keep their
 * `attribute_*` fields in sync with the selection for exactly this purpose
 * (the classic selects directly, the block through hidden inputs bound to
 * its state), so no template-specific DOM walking is needed.
 *
 * @return {{count: number, chosenCount: number, data: Object<string, string>}}
 *         The number of attribute fields, how many of them have a selected
 *         value, and a map of attribute field name to selected value.
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
 * The variation resolved from the current attribute selection, if any.
 *
 * Both templates maintain the hidden `variation_id` input: the classic
 * script writes it on `found_variation`, the blockified block binds it to
 * its Interactivity API state.
 *
 * @return {string|null} The selected variation ID, or null when the
 *                       selection is incomplete or doesn't match a variation.
 */
export const getSelectedVariationId = () => {
	const variationId = document.querySelector(
		'input[name="variation_id"]'
	)?.value;
	if ( ! variationId || variationId === '0' ) {
		return null;
	}

	// The block only rewrites the input when a new selection resolves; it
	// keeps the previous id after an attribute is deselected (the classic
	// script clears it). An id alongside an incomplete selection is stale.
	const { count, chosenCount } = getSelectedVariationAttributes();
	return chosenCount === count ? variationId : null;
};

/**
 * Watches the blockified template for variation selection changes.
 *
 * The block fires no variation event and core declined to add one (its
 * Interactivity API state is the intended signal, but that store is private),
 * so the shopper's gestures on the selector are the only supported signal.
 * The block re-renders the compat `variation_id` input after the gesture, so
 * callers must read the selection after a short delay; core's auto-selection
 * of the remaining attribute runs inside the same gesture and needs no extra
 * signal.
 *
 * The classic template is not covered here: its script fires the jQuery
 * `woocommerce_variation_has_changed` event, which callers listen to directly.
 *
 * @param {Function} onChange Called whenever the selection may have changed.
 * @return {{disconnect: Function}|null} Disconnectable handle, or null when
 *                                       the page has no blockified selector.
 */
export const observeBlockifiedVariationSelection = ( onChange ) => {
	// keyup included: the chips variant selects via a keydown handler on
	// buttons, which fires neither click nor change.
	const gestureEvents = [ 'click', 'change', 'keyup' ];
	const selectors = document.querySelectorAll(
		'.wc-block-add-to-cart-with-options [class*="variation-selector"]'
	);
	if ( ! selectors.length ) {
		return null;
	}

	selectors.forEach( ( el ) =>
		gestureEvents.forEach( ( eventName ) =>
			el.addEventListener( eventName, onChange )
		)
	);

	return {
		disconnect: () => {
			selectors.forEach( ( el ) =>
				gestureEvents.forEach( ( eventName ) =>
					el.removeEventListener( eventName, onChange )
				)
			);
		},
	};
};
