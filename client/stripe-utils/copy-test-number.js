import { __ } from '@wordpress/i18n';
import './copy-test-number.scss';

/**
 * Event-delegation handler for copy-to-clipboard buttons in test mode instructions.
 * Copies the test card/account number and shows a visual success state (icon toggle).
 *
 * Used by both Blocks and classic checkout (imported from each surface's entry point).
 * The snackbar notification only appears on Blocks checkout where the `core/notices`
 * data store renders into the DOM; on classic checkout only the icon toggle provides
 * feedback.
 */
document.addEventListener( 'click', function ( event ) {
	const copyNumberButton = event.target?.closest(
		'.wc-stripe-copy-test-number'
	);
	if ( ! copyNumberButton ) {
		return;
	}

	event.preventDefault();

	const number = copyNumberButton.querySelector( 'span' )?.innerText;
	if ( ! number ) {
		return;
	}

	if ( ! navigator.clipboard?.writeText ) {
		return;
	}

	navigator.clipboard
		.writeText( number.replace( /\s/g, '' ) )
		.then( () => {
			window.wp?.data
				?.dispatch( 'core/notices' )
				?.createInfoNotice(
					__( 'Copied to clipboard!', 'woocommerce-gateway-stripe' ),
					{
						id: 'wc-stripe/test-number-copied',
						type: 'snackbar',
						context: 'wc/checkout/payments',
					}
				);

			copyNumberButton.classList.add( 'state--success' );
			setTimeout(
				() => copyNumberButton.classList.remove( 'state--success' ),
				2000
			);
		} )
		.catch( () => {} );
} );
