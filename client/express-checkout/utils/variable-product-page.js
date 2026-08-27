/**
 * DOM readers for variable products, abstracting over the two product
 * templates: the classic `.variations_form` markup and the blockified
 * Add to Cart + Options block. The block has no public JS API (its
 * Interactivity store is private and has changed shape across releases);
 * the supported surface is the compatibility markup it renders for
 * express payment methods (`AddToCartWithOptions::render()` in core).
 */

/**
 * Whether the page renders the classic `.variations_form` template (as
 * opposed to the blockified Add to Cart + Options block).
 *
 * @return {boolean} True on the classic template.
 */
export const isClassicTemplate = () =>
	Boolean( document.querySelector( '.variations_form' ) );

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
	return variationId && variationId !== '0' ? variationId : null;
};

/**
 * Whether the product cannot currently be added to the cart.
 *
 * No single signal spans every template and supported WooCommerce version:
 * the button's `disabled` class (classic + newer blockified), the blockified
 * form's `is-invalid` class (also covers quantity/stock validity), and an
 * unresolved variation as the fallback.
 *
 * @return {boolean} True when adding to the cart should be blocked.
 */
export const isAddToCartUnavailable = () => {
	const addToCartButton = document.querySelector(
		'.single_add_to_cart_button'
	);

	return Boolean(
		addToCartButton?.classList.contains( 'disabled' ) ||
			document.querySelector(
				'.wc-block-add-to-cart-with-options.is-invalid'
			) ||
			( hasVariationSelectionUi() && ! getSelectedVariationId() )
	);
};

/**
 * Whether the selected attribute combination matches no purchasable
 * variation. Works on both templates, through different signals:
 * - classic: the `wc-variation-is-unavailable` class its script puts on
 *   the add-to-cart button;
 * - blockified: no marker exists, so the verdict is inferred — a complete
 *   attribute selection that resolved no variation matched nothing.
 *   (Out-of-stock combinations resolve an ID, so they correctly stay out
 *   of this bucket.)
 *
 * @return {boolean} True when the selected combination is unavailable.
 */
export const isSelectedVariationUnavailable = () => {
	if ( isClassicTemplate() ) {
		return Boolean(
			document
				.querySelector( '.single_add_to_cart_button' )
				?.classList.contains( 'wc-variation-is-unavailable' )
		);
	}

	const { count, chosenCount } = getSelectedVariationAttributes();
	return count > 0 && chosenCount === count && ! getSelectedVariationId();
};

/**
 * Reads the currently selected variation attributes.
 *
 * @return {Object} count The number of attribute fields.
 *                  chosenCount The number of fields with a selected value.
 *                  data Map of attribute field name to selected value.
 */
export const getSelectedVariationAttributes = () => {
	const data = {};

	const readField = ( field ) => {
		const attributeName =
			field.dataset.attribute_name || field.getAttribute( 'name' );

		// Radio pills render one input per option; only a checked pill (or a
		// select/hidden field, which always carry the selection) has a value.
		if ( ! ( attributeName in data ) ) {
			data[ attributeName ] = '';
		}
		if ( field.type === 'radio' && ! field.checked ) {
			return;
		}
		data[ attributeName ] = field.value || '';
	};

	const classicSelects = document.querySelectorAll(
		'.variations_form .variations select'
	);
	if ( classicSelects.length ) {
		classicSelects.forEach( readField );
	} else {
		// Blockified template. Newer WooCommerce versions render one hidden
		// input per attribute, kept in sync with the selection; older ones
		// (10.7/10.8) don't, but their pill radios and dropdowns carry the
		// same `attribute_*` field names, so read whichever generation exists.
		const hiddenInputs = document.querySelectorAll(
			'.wc-block-add-to-cart-with-options input[type="hidden"][name^="attribute_"]'
		);
		const fields = hiddenInputs.length
			? hiddenInputs
			: document.querySelectorAll(
					'.wc-block-add-to-cart-with-options select[name^="attribute_"], .wc-block-add-to-cart-with-options input[type="radio"][name^="attribute_"]'
			  );
		fields.forEach( readField );
	}

	const values = Object.values( data );

	return {
		count: values.length,
		chosenCount: values.filter( ( value ) => value.length > 0 ).length,
		data,
	};
};

/**
 * Watches the blockified template for variation selection changes.
 *
 * The blockified block fires no jQuery variation events; the Interactivity
 * API instead keeps the compat `variation_id` input's value attribute in
 * sync, which is observable through DOM mutations.
 *
 * @param {Function} onChange Called whenever the selected variation changes.
 * @return {MutationObserver|null} The observer (so callers can disconnect
 *                                 it), or null when the input isn't present.
 */
export const observeVariationSelection = ( onChange ) => {
	const variationIdInput = document.querySelector(
		'input[name="variation_id"]'
	);
	if ( ! variationIdInput ) {
		return null;
	}

	const observer = new MutationObserver( onChange );
	observer.observe( variationIdInput, {
		attributes: true,
		attributeFilter: [ 'value' ],
	} );
	return observer;
};

/**
 * Watches the blockified template's quantity stepper buttons.
 *
 * The steppers update the input's value property only — no input event, no
 * attribute write — so the button press is the only observable signal.
 * Typed changes fire input events and are handled by callers directly.
 *
 * @param {Function} onChange Called whenever a stepper button is pressed.
 * @return {{disconnect: Function}|null} Disconnectable handle, or null on
 *                                       the classic template.
 */
export const observeQuantitySteppers = ( onChange ) => {
	const quantityContainer = document.querySelector(
		'.wc-block-add-to-cart-with-options .quantity'
	);
	if ( ! quantityContainer ) {
		return null;
	}

	const handleClick = ( event ) => {
		if ( event.target.closest( 'button' ) ) {
			onChange();
		}
	};
	quantityContainer.addEventListener( 'click', handleClick );

	return {
		disconnect: () =>
			quantityContainer.removeEventListener( 'click', handleClick ),
	};
};
