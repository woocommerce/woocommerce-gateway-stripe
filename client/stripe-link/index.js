const showLinkButton = ( linkAutofill ) => {
	// Display StripeLink button if email field is prefilled.
	if ( jQuery( '#billing_email' ).val() !== '' ) {
		const linkButtonTop =
			jQuery( '#billing_email' ).position().top +
			( jQuery( '#billing_email' ).outerHeight() - 40 ) / 2;
		jQuery( '.stripe-gateway-stripelink-modal-trigger' ).show();
		jQuery( '.stripe-gateway-stripelink-modal-trigger' ).css(
			'top',
			linkButtonTop + 'px'
		);
	}

	// Handle StripeLink button click.
	jQuery( '.stripe-gateway-stripelink-modal-trigger' ).on(
		'click',
		( event ) => {
			event.preventDefault();
			// Trigger modal.
			linkAutofill.launch( { email: jQuery( '#billing_email' ).val() } );
		}
	);
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
			const shippingNames = shippingAddress.name.split( / (.*)/s, 2 );
			shippingAddress.address.last_name = shippingNames[ 1 ] ?? '';
			shippingAddress.address.first_name = shippingNames[ 0 ];

			fillWith( shippingAddress, options.shipping_fields.line1, 'line1' );
			fillWith( shippingAddress, options.shipping_fields.line2, 'line2' );
			fillWith( shippingAddress, options.shipping_fields.city, 'city' );
			fillWith(
				shippingAddress,
				options.shipping_fields.country,
				'country'
			);
			fillWith(
				shippingAddress,
				options.shipping_fields.first_name,
				'first_name'
			);
			fillWith(
				shippingAddress,
				options.shipping_fields.last_name,
				'last_name'
			);
			jQuery(
				'#billing_country, #billing_state, #shipping_country, #shipping_state'
			).trigger( 'change' );
			fillWith( shippingAddress, options.shipping_fields.state, 'state' );
			fillWith(
				shippingAddress,
				options.shipping_fields.postal_code,
				'postal_code'
			);
		}

		if ( options.complete_billing() ) {
			const billingNames = billingAddress.name.split( / (.*)/s, 2 );
			billingAddress.address.last_name = billingNames[ 1 ] ?? '';
			billingAddress.address.first_name = billingNames[ 0 ];

			fillWith( billingAddress, options.billing_fields.line1, 'line1' );
			fillWith( billingAddress, options.billing_fields.line2, 'line2' );
			fillWith( billingAddress, options.billing_fields.city, 'city' );
			fillWith(
				billingAddress,
				options.billing_fields.country,
				'country'
			);
			fillWith(
				billingAddress,
				options.billing_fields.first_name,
				'first_name'
			);
			fillWith(
				billingAddress,
				options.billing_fields.last_name,
				'last_name'
			);
			jQuery(
				'#billing_country, #billing_state, #shipping_country, #shipping_state'
			).trigger( 'change' );
			fillWith( billingAddress, options.billing_fields.state, 'state' );
			fillWith(
				billingAddress,
				options.billing_fields.postal_code,
				'postal_code'
			);
		}
		jQuery(
			'#billing_country, #billing_state, #shipping_country, #shipping_state'
		).trigger( 'change' );
	} );
};

export default enableStripeLinkPaymentMethod;
