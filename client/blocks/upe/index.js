import {
	registerPaymentMethod,
	registerExpressPaymentMethod,
} from '@woocommerce/blocks-registry';
import { getPaymentMethodsConstants } from '../../stripe-utils/constants';
import Icons from '../../payment-method-icons';
import { getDeferredIntentCreationUPEFields } from './upe-deferred-intent-creation/payment-elements.js';
import { SavedTokenHandler } from './saved-token-handler';
import paymentRequestPaymentMethod from 'wcstripe/blocks/payment-request';
import WCStripeAPI from 'wcstripe/api';
import { getBlocksConfiguration } from 'wcstripe/blocks/utils';
import './styles.scss';

const api = new WCStripeAPI(
	getBlocksConfiguration(),
	// A promise-based interface to jQuery.post.
	( url, args ) => {
		return new Promise( ( resolve, reject ) => {
			jQuery.post( url, args ).then( resolve ).fail( reject );
		} );
	}
);

const upeMethods = getPaymentMethodsConstants();
Object.entries( getBlocksConfiguration()?.paymentMethodsConfig )
	.filter( ( [ upeName ] ) => upeName !== 'link' )
	.forEach( ( [ upeName, upeConfig ] ) => {
		let iconName = upeName;

		// Afterpay/Clearpay have different icons for UK merchants.
		if ( upeName === 'afterpay_clearpay' ) {
			iconName =
				getBlocksConfiguration()?.accountCountry === 'GB'
					? 'clearpay'
					: 'afterpay';
		}

		const Icon = Icons[ iconName ];

		registerPaymentMethod( {
			name: upeMethods[ upeName ],
			content: getDeferredIntentCreationUPEFields(
				upeName,
				upeMethods,
				api,
				upeConfig.testingInstructions,
				upeConfig.showSaveOption ?? false
			),
			edit: getDeferredIntentCreationUPEFields(
				upeName,
				upeMethods,
				api,
				upeConfig.testingInstructions,
				upeConfig.showSaveOption ?? false
			),
			savedTokenComponent: <SavedTokenHandler api={ api } />,
			canMakePayment: ( cartData ) => {
				const billingCountry = cartData.billingAddress.country;
				const isRestrictedInAnyCountry = !! upeConfig.countries.length;
				const isAvailableInTheCountry =
					! isRestrictedInAnyCountry ||
					upeConfig.countries.includes( billingCountry );

				return isAvailableInTheCountry && !! api.getStripe();
			},
			// see .wc-block-checkout__payment-method styles in blocks/style.scss
			label: (
				<>
					<span>
						{ upeConfig.title }
						<Icon alt={ upeConfig.title } />
					</span>
				</>
			),
			ariaLabel: 'Stripe',
			supports: {
				// Use `false` as fallback values in case server provided configuration is missing.
				showSavedCards:
					getBlocksConfiguration()?.showSavedCards ?? false,
				showSaveOption: upeConfig.showSaveOption ?? false,
				features: getBlocksConfiguration()?.supports ?? [],
			},
		} );
	} );

// Register Stripe Payment Request.
registerExpressPaymentMethod( paymentRequestPaymentMethod );
