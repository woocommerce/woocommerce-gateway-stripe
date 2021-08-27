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
//  import './setup-complete-task.scss';
import './style.scss';

 const SetupComplete = () => {
	 const handleGoHome = useCallback( () => {
		 getHistory().push( getNewPath( {}, '/', {} ) );
	 }, [] );

	 return (
		 <WizardTaskItem
			 className="setup-complete-task"
			 title={ __( 'Setup complete', 'woocommerce-gateway-stripe' ) }
			 index={ 3 }
		 >
			 <CollapsibleBody>
				 <p className="wcpay-wizard-task__description-element is-muted-color">
					 { __(
						 "You're ready to begin accepting payments with the new methods!",
						 'woocommerce-gateway-stripe'
					 ) }
				 </p>
				 <p className="wcpay-wizard-task__description-element is-muted-color">
					 { interpolateComponents( {
						 mixedString: __(
							 '{{setupTaxesLink /}} to ensure smooth transactions if you plan to sell to customers in Europe.',
							 'woocommerce-gateway-stripe'
						 ),
						 components: {
							 setupTaxesLink: (
								 <a href="admin.php?page=wc-settings&tab=tax">
									 { __(
										 'Set up taxes',
										 'woocommerce-gateway-stripe'
									 ) }
								 </a>
							 ),
						 },
					 } ) }
				 </p>
				 <p className="wcpay-wizard-task__description-element is-muted-color">
					 { __(
						 'To manage other payment settings or update your payment information, visit the payment settings.',
						 'woocommerce-gateway-stripe'
					 ) }
				 </p>
				 <div className="setup-complete-task__buttons">
					 <Button onClick={ handleGoHome } isPrimary>
						 { __(
							 'Go to WooCommerce Home',
							 'woocommerce-gateway-stripe'
						 ) }
					 </Button>
					 <Button
						 href="admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments"
						 isTertiary
					 >
						 { __(
							 'View payment settings',
							 'woocommerce-gateway-stripe'
						 ) }
					 </Button>
				 </div>
			 </CollapsibleBody>
		 </WizardTaskItem>
	 );
 };

 export default SetupComplete;
