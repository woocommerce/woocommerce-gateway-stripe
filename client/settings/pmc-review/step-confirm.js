import React from 'react';
import InlineNotice from '../../components/inline-notice';
import MethodLabel from './method-label';
import { Button, Card, CardBody, CardFooter } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const hasChanges = ( changes ) =>
	changes.enabled.length > 0 || changes.disabled.length > 0;

const MethodGroup = ( { title, ids } ) => {
	if ( ! ids.length ) {
		return null;
	}
	return (
		<div className="wc-stripe-pmc-review__group">
			<h4 className="wc-stripe-pmc-review__group-title">{ title }</h4>
			<ul className="wc-stripe-pmc-review__group-list">
				{ ids.map( ( id ) => (
					<li key={ id }>
						<MethodLabel id={ id } size="small" />
					</li>
				) ) }
			</ul>
		</div>
	);
};

const ChangesSection = ( { title, changes, emptyText } ) => (
	<section className="wc-stripe-pmc-review__changes">
		<h3 className="wc-stripe-pmc-review__changes-title">{ title }</h3>
		{ hasChanges( changes ) ? (
			<>
				<MethodGroup
					title={ __(
						'Will be enabled',
						'woocommerce-gateway-stripe'
					) }
					ids={ changes.enabled }
				/>
				<MethodGroup
					title={ __(
						'Will be disabled',
						'woocommerce-gateway-stripe'
					) }
					ids={ changes.disabled }
				/>
			</>
		) : (
			<p className="wc-stripe-pmc-review__empty">{ emptyText }</p>
		) }
	</section>
);

const StepConfirm = ( {
	stripeChanges,
	wooChanges,
	error,
	isBusy,
	hasUnsavedChanges,
	onBack,
	onSave,
	onRefresh,
} ) => (
	<Card className="wc-stripe-pmc-review wc-stripe-pmc-review--confirm">
		<CardBody>
			<div className="wc-stripe-pmc-review__intro">
				<h2>
					{ __( 'Confirm changes', 'woocommerce-gateway-stripe' ) }
				</h2>
				<p>
					{ __(
						'Here’s how WooCommerce and your Stripe account will be updated so they match again. From then on, changes you make in either place apply to both.',
						'woocommerce-gateway-stripe'
					) }
				</p>
			</div>
			{ hasChanges( stripeChanges ) && (
				<InlineNotice status="warning" isDismissible={ false }>
					{ __(
						'These changes apply to your Stripe account’s payment method configuration. Every other store connected to this Stripe account will get them too.',
						'woocommerce-gateway-stripe'
					) }
				</InlineNotice>
			) }
			<div className="wc-stripe-pmc-review__columns">
				<ChangesSection
					title={ __( 'In Stripe', 'woocommerce-gateway-stripe' ) }
					changes={ stripeChanges }
					emptyText={ __(
						'No changes in Stripe.',
						'woocommerce-gateway-stripe'
					) }
				/>
				<ChangesSection
					title={ __(
						'In WooCommerce',
						'woocommerce-gateway-stripe'
					) }
					changes={ wooChanges }
					emptyText={ __(
						'No changes in WooCommerce.',
						'woocommerce-gateway-stripe'
					) }
				/>
			</div>
			{ hasUnsavedChanges && (
				<p>
					{ __(
						'Save or discard your other changes first.',
						'woocommerce-gateway-stripe'
					) }
				</p>
			) }
			{ error && (
				<InlineNotice
					status="error"
					isDismissible={ false }
					actions={ [
						{
							label: __(
								'Refresh',
								'woocommerce-gateway-stripe'
							),
							onClick: onRefresh,
						},
					] }
				>
					{ error }
				</InlineNotice>
			) }
		</CardBody>
		<CardFooter className="wc-stripe-pmc-review__footer">
			<Button variant="secondary" disabled={ isBusy } onClick={ onBack }>
				{ __( 'Back', 'woocommerce-gateway-stripe' ) }
			</Button>
			<Button
				variant="primary"
				isBusy={ isBusy }
				disabled={ isBusy || hasUnsavedChanges }
				onClick={ onSave }
			>
				{ __(
					'I confirm, save changes',
					'woocommerce-gateway-stripe'
				) }
			</Button>
		</CardFooter>
	</Card>
);

export default StepConfirm;
