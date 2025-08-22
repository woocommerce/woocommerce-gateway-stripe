import { getAdminLink } from '@woocommerce/settings';
import { __ } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';
import { getQuery } from '@woocommerce/navigation';
import {
	getStorageWithExpiration,
	setStorageWithExpiration,
} from 'wcstripe/stripe-utils/utils';

const LOCAL_STORAGE_KEY = 'wc_stripe_is_onboarding_through_wc_setup';
const EXPIRATION_TIME = 60 * 60 * 1000; // 1 hour in milliseconds

const query = new URLSearchParams( window.location.search );
const from = query.get( 'from' );

const newAccountContainer = document.getElementById(
	'wc-stripe-new-account-container'
);

// If the new merchant is onboarding through WC Setup wizard and coming to Stripe Settings page
// from the WC payments settings page, set a flag in local storage
// to take them back to the onboarding flow after connecting their account.
// This flag is needed because, when the merchant return back from Stripe, we lose the `from` query param.
const isPaymentOnboardingTaskComplete =
	window.wc_stripe_settings_params?.is_payments_onboarding_task_completed ===
	'1';
if (
	from === 'WCADMIN_PAYMENT_SETTINGS' &&
	newAccountContainer &&
	! isPaymentOnboardingTaskComplete
) {
	setStorageWithExpiration( LOCAL_STORAGE_KEY, 'true', EXPIRATION_TIME );
}

const shouldShowNotice = () => {
	const { wc_stripe_connected: stripeAccountConnected } = getQuery();
	const isOnboardingThroughWCSetup = getStorageWithExpiration(
		LOCAL_STORAGE_KEY
	);

	return stripeAccountConnected && isOnboardingThroughWCSetup;
};

const StripeAccountConnectedNotice = () => {
	if ( shouldShowNotice() ) {
		localStorage.removeItem( LOCAL_STORAGE_KEY );
		dispatch( 'core/notices' ).createSuccessNotice(
			__( 'Stripe Account Connected', 'woocommerce-gateway-stripe' ),
			{
				id: 'WOOCOMMERCE_STRIPE_ACCOUNT_CONNECTED_NOTICE',
				actions: [
					{
						url: getAdminLink( 'admin.php?page=wc-admin' ),
						label: __(
							'Continue setting up your store',
							'woocommerce-gateway-stripe'
						),
					},
				],
			}
		);
	}

	return null;
};

export default StripeAccountConnectedNotice;
