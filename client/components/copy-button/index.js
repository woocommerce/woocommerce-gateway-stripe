import { useState, useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import './style.scss';

/**
 * A reusable copy-to-clipboard button.
 *
 * Renders an inline button with a copy icon that copies the provided `text`
 * to the clipboard on click. Shows a check-mark icon for 2 seconds on success.
 *
 * @param {Object} props
 * @param {string} props.text        The text to copy to the clipboard.
 * @param {string} [props.label]     Accessible label for the button (defaults to "Copy").
 * @param {string} [props.className] Additional CSS class names.
 */
const CopyButton = ( { text, label, className = '' } ) => {
	const [ copied, setCopied ] = useState( false );

	const handleClick = useCallback( () => {
		if ( ! navigator.clipboard?.writeText || ! text ) {
			return;
		}

		navigator.clipboard.writeText( text ).then( () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 2000 );
		} );
	}, [ text ] );

	return (
		<button
			type="button"
			className={ `wc-stripe-copy-button ${
				copied ? 'state--success' : ''
			} ${ className }`.trim() }
			onClick={ handleClick }
			aria-label={ label || __( 'Copy', 'woocommerce-gateway-stripe' ) }
		>
			<i />
		</button>
	);
};

export default CopyButton;
