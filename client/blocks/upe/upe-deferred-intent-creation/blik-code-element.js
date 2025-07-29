import { ValidatedTextInput } from '@woocommerce/blocks-checkout';
import { _x } from '@wordpress/i18n';
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
						return _x(
							'Please enter a valid BLIK code',
							'shopper',
							'woocommerce-gateway-stripe'
						);
					}

					if ( validity.patternMismatch ) {
						return _x(
							'BLIK Code is invalid',
							'shopper',
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
				{ _x(
					'After submitting your order, please authorize the payment in your mobile banking application.',
					'shopper',
					'woocommerce-gateway-stripe'
				) }
			</p>
		</>
	);
};

export default BlikCodeElement;
