/**
 * Block UI to indicate processing and avoid duplicate submission.
 *
 * @param {Object} jQueryForm The jQuery object for the form.
 */
export function blockCheckoutForm( jQueryForm ) {
	jQueryForm.addClass( 'processing' ).block( {
		message: null,
		overlayCSS: {
			background: '#fff',
			opacity: 0.6,
		},
	} );
}

export function unblockCheckoutForm( jQueryForm ) {
	jQueryForm.removeClass( 'processing' ).unblock();
}
