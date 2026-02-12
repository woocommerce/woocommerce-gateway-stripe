<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Stripe class. Temporarily extends WC_Stripe_UPE_Payment_Gateway for backward compatibility.
 *
 * @deprecated Deprecated in favor of WC_Stripe_UPE_Payment_Gateway. To be removed in a future version.
 */
class WC_Gateway_Stripe extends WC_Stripe_UPE_Payment_Gateway {
}
