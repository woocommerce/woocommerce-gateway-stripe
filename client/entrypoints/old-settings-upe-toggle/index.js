/* global wc_stripe_old_settings_param */
import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { recordEvent } from 'wcstripe/tracking';

domReady( () => {
	// eslint-disable-next-line camelcase
	if ( ! wc_stripe_old_settings_param ) {
		return;
	}

	const {
		was_upe_enabled: wasUpeEnabled,
		is_upe_enabled: isUpeEnabled,
		// eslint-disable-next-line camelcase
	} = wc_stripe_old_settings_param;

	if ( isUpeEnabled !== '1' ) {
		dispatch( 'core/notices' ).createSuccessNotice(
			__(
				'🤔 What made you disable the new payments experience?',
				'woocommerce-gateway-stripe'
			),
			{
				actions: [
					{
						label: __(
							'Share feedback (1 min)',
							'woocommerce-gateway-stripe'
						),
						url:
							'https://woocommerce.survey.fm/woocommerce-stripe-upe-opt-out-survey',
					},
				],
			}
		);
	}

	if ( wasUpeEnabled !== isUpeEnabled ) {
		const eventName = isUpeEnabled
			? 'wcstripe_upe_enabled'
			: 'wcstripe_upe_disabled';
		recordEvent( eventName );
	}
} );
