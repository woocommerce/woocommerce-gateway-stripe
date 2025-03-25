import { ValidatedTextInput } from '@woocommerce/blocks-checkout';
import { __ } from '@wordpress/i18n';
import { useState } from 'react';

const BlikCodeElement = () => {
	const [ blikCode, setBlikCode ] = useState( '' );

	return (
		<>
			<ValidatedTextInput
				id="wc-stripe-blik-code"
				label="BLIK Code"
				maxLength={ 6 }
				onChange={ setBlikCode }
				pattern="[0-9]{6}"
				value={ blikCode }
				customValidityMessage={ ( validity ) => {
					if ( validity.valueMissing ) {
						return __(
							'Please enter a valid BLIK code',
							'woocommerce-gateway-stripe'
						);
					}

					if ( validity.patternMismatch ) {
						return __(
							'BLIK Code is invalid',
							'woocommerce-gateway-stripe'
						);
					}
				} }
				required
			/>
			<p
				style={ {
					marginTop: 'var(--wp--preset--spacing--50)',
				} }
			>
				{ __(
					'After submitting your order, please authorize the payment in your mobile banking application.',
					'woocommerce-gateway-stripe'
				) }
			</p>
		</>
	);
};

export default BlikCodeElement;
