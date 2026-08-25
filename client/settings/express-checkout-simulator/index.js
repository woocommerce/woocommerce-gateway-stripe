import React from 'react';
import {
	Icon,
	check as checkIcon,
	close as closeIcon,
	info as infoIcon,
} from '@wordpress/icons';
import { STATUS } from './build-checks';
import { VisuallyHidden } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './style.scss';

const STATUS_ICON = {
	[ STATUS.PASS ]: checkIcon,
	[ STATUS.FAIL ]: closeIcon,
	[ STATUS.INFO ]: infoIcon,
};

// Screen-reader status per row; the icon and modifier class are not accessible.
const STATUS_TEXT = {
	[ STATUS.PASS ]: __( 'Passed:', 'woocommerce-gateway-stripe' ),
	[ STATUS.FAIL ]: __( 'Failed:', 'woocommerce-gateway-stripe' ),
	[ STATUS.INFO ]: __( 'Information:', 'woocommerce-gateway-stripe' ),
};

/**
 * Admin-only simulator that explains, for the current settings, where an express checkout
 * button would and wouldn't appear and why. It complements the live `ExpressCheckoutPreview`
 * (which only proves the button can render in the admin's browser) by surfacing the
 * configuration-level gates that decide storefront placement.
 *
 * Presentational only: the caller computes every gate (`checks`) and the per-location toggle
 * state (`locations`) so this component stays free of page-specific data stores. The first
 * failing check that carries `blockingText` hides the button at every location; otherwise a
 * location shows only when its toggle is enabled.
 *
 * @param {Object}        props
 * @param {Array<Object>} props.checks    Ordered eligibility checks; see `build-checks.js`.
 * @param {Array<Object>} props.locations `{ key, label, enabled }` for the tab's locations.
 */
const ExpressCheckoutSimulator = ( { checks, locations } ) => {
	// The first failing check with blocking text gates every location; checks are ordered by
	// precedence, so the earliest failure is the reason a merchant sees.
	const blocker = checks.find(
		( check ) => check.status === STATUS.FAIL && check.blockingText
	);

	const getLocationVerdict = ( location ) => {
		if ( blocker ) {
			return { shown: false, reason: blocker.blockingText };
		}
		if ( ! location.enabled ) {
			return {
				shown: false,
				reason: __(
					'Not enabled for this location in the settings above.',
					'woocommerce-gateway-stripe'
				),
			};
		}
		return {
			shown: true,
			reason: __( 'Would display here.', 'woocommerce-gateway-stripe' ),
		};
	};

	return (
		<div className="express-checkout-simulator">
			<h4>{ __( 'Eligibility', 'woocommerce-gateway-stripe' ) }</h4>
			<ul className="express-checkout-simulator__checks">
				{ checks.map( ( check ) => (
					<li
						key={ check.key }
						className={ `express-checkout-simulator__check is-${ check.status }` }
					>
						<Icon
							icon={ STATUS_ICON[ check.status ] }
							size={ 20 }
						/>
						<VisuallyHidden>
							{ STATUS_TEXT[ check.status ] }
						</VisuallyHidden>
						<span className="express-checkout-simulator__check-label">
							{ check.label }
						</span>
						{ check.detail && (
							<span className="express-checkout-simulator__check-detail">
								{ check.detail }
							</span>
						) }
					</li>
				) ) }
			</ul>

			<h4>
				{ __(
					'Where the button appears',
					'woocommerce-gateway-stripe'
				) }
			</h4>
			<ul className="express-checkout-simulator__locations">
				{ locations.map( ( location ) => {
					const verdict = getLocationVerdict( location );
					return (
						<li
							key={ location.key }
							className={ `express-checkout-simulator__location is-${
								verdict.shown ? 'shown' : 'hidden'
							}` }
						>
							<Icon
								icon={ verdict.shown ? checkIcon : closeIcon }
								size={ 20 }
							/>
							<span className="express-checkout-simulator__location-label">
								{ location.label }
							</span>
							<span className="express-checkout-simulator__location-reason">
								{ verdict.reason }
							</span>
						</li>
					);
				} ) }
			</ul>

			<p className="express-checkout-simulator__caveat">
				{ __(
					'This simulation reflects the settings currently shown on this page, including unsaved changes. On the storefront the button can still be hidden by checks that can only run there — a supported device and browser, the contents and product types in the cart, and taxes that need a customer address.',
					'woocommerce-gateway-stripe'
				) }
			</p>
		</div>
	);
};

export default ExpressCheckoutSimulator;
