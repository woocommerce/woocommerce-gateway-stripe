/**
 * Block UI to indicate processing and avoid duplicate submission.
 *
 * @param {Object} jQueryForm The jQuery object for the form.
 */
export function blockUI( jQueryForm ) {
	jQueryForm.addClass( 'processing' ).block( {
		message: null,
		overlayCSS: {
			background: '#fff',
			opacity: 0.6,
		},
	} );
}
