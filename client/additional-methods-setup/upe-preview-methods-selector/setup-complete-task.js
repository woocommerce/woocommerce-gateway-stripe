/**
 * External dependencies
 */
 import React from 'react';
 import { useEffect, useCallback, useContext } from '@wordpress/element';
 import { __ } from '@wordpress/i18n';
 import { Button } from '@wordpress/components';
 import { getHistory, getNewPath } from '@woocommerce/navigation';
//  import { useDispatch } from '@wordpress/data';
 import interpolateComponents from 'interpolate-components';

 /**
  * Internal dependencies
  */
 import CollapsibleBody from '../wizard/collapsible-body';
 import WizardTaskItem from '../wizard/task-item';
 import WizardTaskContext from '../wizard/task/context';
 import WizardContext from '../wizard/wrapper/context';
 import { useEnabledPaymentMethodIds } from '../../data';
//  import './setup-complete-task.scss';
import PaymentMethodIcon from '../../settings/payment-method-icon';
import './style.scss';

const SetupCompleteMessaging = () => {
	const [ enabledPaymentMethods ] = useEnabledPaymentMethodIds();
	const enabledMethodsCount = enabledPaymentMethods.length;

	const { completedTasks } = useContext( WizardContext );
	const enableUpePreviewPayload = completedTasks[ 'add-payment-methods' ];

	if ( ! enableUpePreviewPayload ) {
		return null;
	}

	// we need to check that the type of `enableUpePreviewPayload` is an object - it can also just be `true` or `undefined`
	let addedPaymentMethodsCount = 0;
	if (
		'object' === typeof enableUpePreviewPayload &&
		enableUpePreviewPayload.initialMethods
	) {
		const { initialMethods } = enableUpePreviewPayload;
		addedPaymentMethodsCount = enabledMethodsCount - initialMethods.length;
	}

	// can't just check for "0", some methods could have been disabled
	if ( 0 >= addedPaymentMethodsCount ) {
		return __( 'Setup complete!', 'woocommerce-gateway-stripe' );
	}

	return sprintf(
		_n(
			'Setup complete! One new payment method is now live on your store!',
			'Setup complete! %s new payment methods are now live on your store!',
			addedPaymentMethodsCount,
			'woocommerce-gateway-stripe'
		),
		addedPaymentMethodsCount
	);
};

const EnabledMethodsList = () => {
	const [ enabledPaymentMethods ] = useEnabledPaymentMethodIds();

	return (
		<ul className="wcpay-wizard-task__description-element setup-complete-task__enabled-methods-list">
			{ enabledPaymentMethods.map( ( methodId ) => (
				<li key={ methodId }>
					<PaymentMethodIcon name={ methodId } showName={ false } />
				</li>
			) ) }
		</ul>
	);
};

 const SetupComplete = () => {
	 return (
		<WizardTaskItem
		title={ __( 'Enjoy the new features', 'woocommerce-gateway-stripe' ) }
		index={ 3 }
	>
		<CollapsibleBody>
			<p className="wcpay-wizard-task__description-element is-muted-color">
				<SetupCompleteMessaging />
			</p>
			<EnabledMethodsList />
			<div className="setup-complete-task__buttons">
				<Button
					href="admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments"
					isPrimary
				>
					{ __(
						'Go to payments settings',
						'woocommerce-gateway-stripe'
					) }
				</Button>
			</div>
		</CollapsibleBody>
	</WizardTaskItem>
	 );
 };

 export default SetupComplete;
