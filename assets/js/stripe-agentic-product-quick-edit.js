/* global inlineEditPost */

/**
 * Populates the Agentic Commerce exclude checkbox when a product row enters
 * quick edit. WP clones the quick-edit template with every checkbox unchecked,
 * so the current state is copied from the row's hidden inline-data div
 * (rendered by WC_Stripe_Agentic_Commerce_Product_List_Table::render_inline_data).
 * The div only exists for supported product types; without it the field is
 * hidden and disabled so the save handler skips the row entirely.
 */
( function ( $ ) {
	'use strict';

	if ( 'undefined' === typeof inlineEditPost ) {
		return;
	}

	var wpInlineEdit = inlineEditPost.edit;

	inlineEditPost.edit = function ( post ) {
		wpInlineEdit.apply( this, arguments );

		var postId = 0;
		if ( 'object' === typeof post ) {
			postId = parseInt( this.getId( post ), 10 );
		}
		if ( ! postId ) {
			return;
		}

		var $checkbox = $( '#edit-' + postId ).find(
			'input[name="_wc_stripe_agentic_commerce_exclude"]'
		);
		var $marker = $( '#edit-' + postId ).find(
			'input[name="_wc_stripe_agentic_commerce_exclude_present"]'
		);
		var $state = $( '#inline_' + postId ).find(
			'.wc_stripe_agentic_excluded'
		);

		if ( ! $state.length ) {
			// Unsupported product type: hide the field and keep it out of the
			// submission so the server can't mistake it for an unchecked box.
			$checkbox.closest( 'fieldset' ).hide();
			$checkbox.prop( 'disabled', true );
			$marker.prop( 'disabled', true );
			return;
		}

		$checkbox.prop( 'checked', 'yes' === $state.text() );
	};
} )( jQuery );
