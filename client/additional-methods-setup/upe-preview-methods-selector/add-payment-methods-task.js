/**
 * External dependencies
 */
import React, {
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useState,
} from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, ExternalLink } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../wizard/task/context';
import CollapsibleBody from '../wizard/collapsible-body';
import WizardTaskItem from '../wizard/task-item';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useSettings,
} from '../../data';
import PaymentMethodCheckboxes from '../../components/payment-methods-checkboxes';
import PaymentMethodCheckbox from '../../components/payment-methods-checkboxes/payment-method-checkbox';

const AddPaymentMethodsTask = () => {
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();
	const { setCompleted } = useContext( WizardTaskContext );
	const isSaving = false;
	const [ paymentMethodsState, setPaymentMethodsState ] = useState( {
		card: true,
	} );

	const handleContinueClick = useCallback( () => {
		setCompleted( true, 'setup-complete' );
	}, [ setCompleted ] );

	const paymentsCheckboxHandler = ( name, enabled ) => {
		setPaymentMethodsState( {
			...paymentMethodsState,
			...{
				[ name ]: enabled,
			},
		} );
	};

	return (
		<WizardTaskItem
			className="add-payment-methods-task"
			title={ __(
				'Review accepted payment methods',
				'woocommerce-gateway-stripe'
			) }
			index={ 2 }
		>
			<CollapsibleBody>
				<p className="wcpay-wizard-task__description-element is-muted-color">
					{ interpolateComponents( {
						mixedString: __(
							"We've added methods that you'd already enabled. For best results, we recommand adding " +
								"all available payment methods. We'll only show your customers the most relevant payment" +
								'methods based on their location and purchasing history. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
							'woocommerce-gateway-stripe'
						),
						components: {
							learnMoreLink: (
								<ExternalLink href="https://docs.woocommerce.com/document/payments/additional-payment-methods/#available-methods" />
							),
						},
					} ) }
				</p>
				<Card className="add-payment-methods-task__payment-selector-wrapper">
					<CardBody>
						{ /* eslint-disable-next-line max-len */ }
						<p className="add-payment-methods-task__payment-selector-title wcpay-wizard-task__description-element is-headline">
							{ __(
								'Payments accepted at checkout',
								'woocommerce-gateway-stripe'
							) }
						</p>

						<PaymentMethodCheckboxes>
							{ availablePaymentMethods.map(
								( paymentMethodId ) => (
									<PaymentMethodCheckbox
										name={ paymentMethodId }
										onChange={ paymentsCheckboxHandler }
										checked={
											paymentMethodsState[
												paymentMethodId
											]
										}
									></PaymentMethodCheckbox>
								)
							) }
						</PaymentMethodCheckboxes>
					</CardBody>
				</Card>
				<Button
					isBusy={ isSaving }
					disabled={ isSaving }
					onClick={ handleContinueClick }
					isPrimary
				>
					{ __(
						'Add payment methods',
						'woocommerce-gateway-stripe'
					) }
				</Button>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default AddPaymentMethodsTask;
