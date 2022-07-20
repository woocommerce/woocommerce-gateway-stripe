const showLinkButton = ( linkAutofill ) => {
	// Display StripeLink button if email field is prefilled.
	if ( jQuery( '#billing_email' ).val() !== '' ) {
		const linkButtonTop =
			jQuery( '#billing_email' ).position().top +
			( jQuery( '#billing_email' ).outerHeight() - 40 ) / 2;
		jQuery( '.wcpay-stripelink-modal-trigger' ).show();
		jQuery( '.wcpay-stripelink-modal-trigger' ).css(
			'top',
			linkButtonTop + 'px'
		);
	}

	// Handle StripeLink button click.
	jQuery( '.wcpay-stripelink-modal-trigger' ).on( 'click', ( event ) => {
		event.preventDefault();
		// Trigger modal.
		linkAutofill.launch( { email: jQuery( '#billing_email' ).val() } );
	} );
};

const enableStripeLinkPaymentMethod = ( options ) => {
	if ( ! document.getElementById( options.emailId ) ) {
		return;
	}
	const api = options.api;
	const linkAutofill = api.getStripe().linkAutofillModal( options.elements );

	document
		.getElementById( options.emailId )
		.addEventListener( 'keyup', ( event ) => {
			linkAutofill.launch( { email: event.target.value } );
		} );

	const showButton = options.show_button
		? options.show_button
		: showLinkButton;
	showButton( linkAutofill );

	linkAutofill.on( 'autofill', ( event ) => {
		const { billingAddress, shippingAddress } = event.value;
		const fillWith = options.fill_field_method
			? options.fill_field_method
			: ( address, nodeId, key ) => {
					if ( document.getElementById( nodeId ) !== null ) {
						document.getElementById( nodeId ).value =
							address.address[ key ];
					}
			  };

		if ( options.complete_shipping() ) {
			fillWith( shippingAddress, options.shipping_fields.line1, 'line1' );
			fillWith( shippingAddress, options.shipping_fields.line2, 'line2' );
			fillWith( shippingAddress, options.shipping_fields.city, 'city' );
			fillWith( shippingAddress, options.shipping_fields.state, 'state' );
			fillWith(
				shippingAddress,
				options.shipping_fields.postal_code,
				'postal_code'
			);
			fillWith(
				shippingAddress,
				options.shipping_fields.country,
				'country'
			);
		}

		if ( options.complete_billing() ) {
			fillWith( billingAddress, options.billing_fields.line1, 'line1' );
			fillWith( billingAddress, options.billing_fields.line2, 'line2' );
			fillWith( billingAddress, options.billing_fields.city, 'city' );
			fillWith( billingAddress, options.billing_fields.state, 'state' );
			fillWith(
				billingAddress,
				options.billing_fields.postal_code,
				'postal_code'
			);
			fillWith(
				billingAddress,
				options.billing_fields.country,
				'country'
			);
		}
		jQuery( 'select' ).trigger( 'change' );
	} );
};

export default enableStripeLinkPaymentMethod;
