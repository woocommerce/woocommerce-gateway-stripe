/* global inlineEditPost */

/**
 * Populates the Agentic Commerce exclude checkbox when a product row enters
 * quick edit. WP clones the quick-edit template with every checkbox unchecked,
 * so the current state has to be copied from the row's status column
 * (rendered by WC_Stripe_Agentic_Commerce_Product_List_Table::render_column).
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

		var excluded =
			'yes' ===
			$( '#post-' + postId )
				.find( '.column-wc_stripe_agentic_sync [data-excluded]' )
				.attr( 'data-excluded' );

		$( '#edit-' + postId )
			.find( 'input[name="_wc_stripe_agentic_commerce_exclude"]' )
			.prop( 'checked', excluded );
	};
} )( jQuery );
