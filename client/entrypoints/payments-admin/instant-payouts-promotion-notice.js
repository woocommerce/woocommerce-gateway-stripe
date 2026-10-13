import React from 'react';
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/ui';

/**
 * Banner promoting Instant Payouts.
 *
 * @param {Object}   props            Component props.
 * @param {string}   props.message    The instant payout availability message.
 * @param {string}   props.payoutsUrl The Stripe Dashboard payouts URL.
 * @param {Function} props.onDismiss  Called when the merchant dismisses the banner.
 * @return {JSX.Element} The banner.
 */
const InstantPayoutsPromotionNotice = ( {
	message,
	payoutsUrl,
	onDismiss,
} ) => (
	<Notice.Root
		intent="info"
		className="wc-stripe-instant-payouts-promotion-notice"
	>
		<Notice.Title>
			{ __( 'Improve your cash flow', 'woocommerce-gateway-stripe' ) }
		</Notice.Title>
		<Notice.Description>
			{ __(
				'With Instant Payouts, get access to your balance within minutes — even on weekends and holidays.',
				'woocommerce-gateway-stripe'
			) }{ ' ' }
			{ message }
		</Notice.Description>
		<Notice.Actions>
			<Notice.ActionLink href={ payoutsUrl } openInNewTab>
				{ __( 'Pay out instantly', 'woocommerce-gateway-stripe' ) }
			</Notice.ActionLink>
		</Notice.Actions>
		<Notice.CloseIcon
			label={ __( 'Dismiss', 'woocommerce-gateway-stripe' ) }
			onClick={ onDismiss }
		/>
	</Notice.Root>
);

export default InstantPayoutsPromotionNotice;
