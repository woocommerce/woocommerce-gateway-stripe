import React, { useState } from 'react';
import InlineNotice from '../../components/inline-notice';
import MethodLabel from './method-label';
import { getModeLabel, ReasonBadge, StatusBadge } from './badges';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';

const getStripeHeader = ( mode ) =>
	sprintf(
		/* translators: %s: "test mode" or "live mode". */
		__( 'Stripe (%s)', 'woocommerce-gateway-stripe' ),
		getModeLabel( mode )
	);

const LastSeen = ( { timestamp } ) => {
	if ( ! timestamp ) {
		return null;
	}
	return (
		<span className="wc-stripe-pmc-review__header-note">
			{ sprintf(
				/* translators: %s: a short date, e.g. "Nov 12". */
				__( 'Last seen %s', 'woocommerce-gateway-stripe' ),
				dateI18n( 'M j', timestamp * 1000 )
			) }
		</span>
	);
};

const OtherModeStatus = ( { value } ) =>
	value === null || value === undefined ? (
		<span className="wc-stripe-pmc-review__unknown">—</span>
	) : (
		<StatusBadge isOn={ value } />
	);

const Intro = ( { review } ) => (
	<div className="wc-stripe-pmc-review__intro">
		<h2>
			{ __(
				'Review your payment methods',
				'woocommerce-gateway-stripe'
			) }
		</h2>
		<p>
			{ __(
				'WooCommerce and your Stripe account normally share the same payment method settings, but right now they don’t match. Your customers might see different payment options from the ones you have selected.',
				'woocommerce-gateway-stripe'
			) }
		</p>
		<p>
			{ __(
				'Until you finish this review, checkout uses the payment methods saved in WooCommerce.',
				'woocommerce-gateway-stripe'
			) }
		</p>
		<p>
			{ __(
				'Below, choose which payment methods should be on. We preselected each one based on where we think it was last changed, so please double-check them. In the next step you can check exactly what will change before anything is saved.',
				'woocommerce-gateway-stripe'
			) }
		</p>
		{ review.mode_switched_from && (
			<InlineNotice status="info" isDismissible={ false }>
				{ sprintf(
					/* translators: 1: previous mode, 2: current mode, e.g. "test mode" and "live mode". */
					__(
						'You switched from %1$s to %2$s, so the saved payment methods came from %1$s. The extra column shows what %1$s had in Stripe, so you can compare.',
						'woocommerce-gateway-stripe'
					),
					getModeLabel( review.mode_switched_from ),
					getModeLabel( review.mode )
				) }
			</InlineNotice>
		) }
	</div>
);

const StepReview = ( { review, rows, isOn, onToggle, onContinue } ) => {
	const [ showAll, setShowAll ] = useState( false );
	const otherMode = review.mode_switched_from;

	const isHideable = ( row ) => row.reason === 'match' && ! row.differs;
	const hiddenCount = rows.filter( isHideable ).length;
	const visibleRows = showAll
		? rows
		: rows.filter( ( row ) => ! isHideable( row ) );

	return (
		<Card className="wc-stripe-pmc-review">
			<CardBody>
				<Intro review={ review } />
				<table className="wc-stripe-pmc-review__table widefat">
					<thead>
						<tr>
							<th>
								{ __(
									'Payment method',
									'woocommerce-gateway-stripe'
								) }
							</th>
							<th>
								{ __(
									'WooCommerce',
									'woocommerce-gateway-stripe'
								) }
							</th>
							<th>
								{ otherMode
									? getStripeHeader( review.mode )
									: __(
											'Stripe',
											'woocommerce-gateway-stripe'
									  ) }
							</th>
							{ otherMode && (
								<th>
									{ getStripeHeader( otherMode ) }
									<LastSeen
										timestamp={
											review.other_mode_synced_at
										}
									/>
								</th>
							) }
							<th>
								{ __(
									'Your choice',
									'woocommerce-gateway-stripe'
								) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ visibleRows.map( ( row ) => (
							<tr key={ row.id }>
								<td>
									<MethodLabel id={ row.id }>
										<ReasonBadge
											reason={ row.reason }
											mode={ review.mode }
										/>
									</MethodLabel>
								</td>
								<td>
									<StatusBadge isOn={ row.local } />
								</td>
								<td>
									<StatusBadge isOn={ row.stripe } />
								</td>
								{ otherMode && (
									<td>
										<OtherModeStatus
											value={ row.other_mode_stripe }
										/>
									</td>
								) }
								<td>
									<ToggleControl
										__nextHasNoMarginBottom
										label={
											isOn( row )
												? __(
														'On',
														'woocommerce-gateway-stripe'
												  )
												: __(
														'Off',
														'woocommerce-gateway-stripe'
												  )
										}
										checked={ isOn( row ) }
										disabled={ ! row.overridable }
										help={
											row.overridable
												? undefined
												: __(
														'This payment method can’t be changed here.',
														'woocommerce-gateway-stripe'
												  )
										}
										onChange={ ( checked ) =>
											onToggle( row.id, checked )
										}
									/>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
				{ hiddenCount > 0 && (
					<Button
						variant="link"
						onClick={ () => setShowAll( ! showAll ) }
					>
						{ showAll
							? __(
									'Hide unchanged payment methods',
									'woocommerce-gateway-stripe'
							  )
							: sprintf(
									/* translators: %d: number of hidden payment methods. */
									__(
										'Show unchanged payment methods (%d)',
										'woocommerce-gateway-stripe'
									),
									hiddenCount
							  ) }
					</Button>
				) }
			</CardBody>
			<CardFooter className="wc-stripe-pmc-review__footer">
				<Button variant="primary" onClick={ onContinue }>
					{ __( 'Continue', 'woocommerce-gateway-stripe' ) }
				</Button>
			</CardFooter>
		</Card>
	);
};

export default StepReview;
