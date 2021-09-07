/**
 * External dependencies
 */
import React, { useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, ExternalLink } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../wizard/task/context';
import CollapsibleBody from '../wizard/collapsible-body';
import WizardTaskItem from '../wizard/task-item';
import { useEnabledPaymentMethodIds, useGetAvailablePaymentMethodIds, useSettings } from '../../data';
import PaymentMethodCheckboxes from '../../components/payment-methods-checkboxes';
import PaymentMethodCheckbox from '../../components/payment-methods-checkboxes/payment-method-checkbox';
import { LoadableBlock } from '../../components/loadable';
import LoadableSettingsSection from '../../settings/loadable-settings-section';
import './style.scss';

const upeMethods = [
	'bancontact',
	'giropay',
	'ideal',
	'p24',
	'sepa_debit',
	'sofort',
];

const usePaymentMethodsCheckboxState = () => {
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();
	const [ enabledPaymentMethodIds ] = useEnabledPaymentMethodIds();
	const [ paymentMethodsState, setPaymentMethodsState ] = useState( {} );

	useEffect( () => {
		setPaymentMethodsState(
			// by default, only the checkboxes for methods enabled prior to enabling UPE should be checked
			availablePaymentMethods
				.filter( ( method ) => upeMethods.includes( method ) )
				.reduce(
					( map, paymentMethod ) => {
						const isEnabledAsNonUpeGateway = window.wc_stripe_onboarding_params.enabled_non_upe_gateway_ids.includes( paymentMethod );
						const isEnabledInUpe = enabledPaymentMethodIds.includes( paymentMethod );

						return {
							...map,
							[ paymentMethod ]: isEnabledAsNonUpeGateway || isEnabledInUpe,
						};
					},
					{}
				)
		);
	}, [ availablePaymentMethods, enabledPaymentMethodIds, setPaymentMethodsState ] );

	const handleChange = useCallback(
		( paymentMethodName, enabled ) => {
			setPaymentMethodsState( ( oldValues ) => ( {
				...oldValues,
				[ paymentMethodName ]: enabled,
			} ) );
		},
		[ setPaymentMethodsState ]
	);

	return [ paymentMethodsState, handleChange ];
};

const ContinueButton = ( { paymentMethodsState } ) => {
	const { setCompleted } = useContext( WizardTaskContext );
	const [
		initialEnabledPaymentMethodIds,
		updateEnabledPaymentMethodIds,
	] = useEnabledPaymentMethodIds();

	const { saveSettings, isSaving } = useSettings();

	const checkedPaymentMethods = useMemo(
		() =>
			Object.entries( paymentMethodsState )
				.map( ( [ method, enabled ] ) => enabled && method )
				.filter( Boolean ),
		[ paymentMethodsState ]
	);

	const unCheckedPaymentMethods = Object.entries( paymentMethodsState )
		.map( ( [ method, enabled ] ) => ! enabled && method )
		.filter( Boolean );

	const handleContinueClick = useCallback( () => {
		// creating a separate callback, so that the main thread isn't blocked on click of the button
		const callback = async () => {
			updateEnabledPaymentMethodIds( [
				// adding the newly selected payment methods and removing them from the `initialEnabledPaymentMethodIds` if unchecked
				...new Set(
					[
						...initialEnabledPaymentMethodIds,
						...checkedPaymentMethods,
					].filter(
						( method ) =>
							! unCheckedPaymentMethods.includes( method )
					)
				),
			] );

			const isSuccess = await saveSettings();
			if ( ! isSuccess ) {
				// restoring the state, in case of soft route
				updateEnabledPaymentMethodIds( initialEnabledPaymentMethodIds );
				return;
			}

			setCompleted(
				{
					initialMethods: initialEnabledPaymentMethodIds,
				},
				'setup-complete'
			);
		};

		callback();
	}, [
		unCheckedPaymentMethods,
		checkedPaymentMethods,
		updateEnabledPaymentMethodIds,
		saveSettings,
		setCompleted,
		initialEnabledPaymentMethodIds,
	] );

	return (
		<Button
			isBusy={ isSaving }
			disabled={ isSaving || 1 > checkedPaymentMethods.length }
			onClick={ handleContinueClick }
			isPrimary
		>
			{ __( 'Add payment methods', 'woocommerce-gateway-stripe' ) }
		</Button>
	);
};

const SelectAllButton = ( { methods, setMethodState } ) => {
	const handleClick = useCallback(
		() => {
			Object.keys( methods )
				.forEach( ( method ) => {
					setMethodState( method, true );
				} );
		},
		[ methods, setMethodState ]
	);

	return <Button isLink onClick={ handleClick } className="add-payment-methods-task__select-all-button">
		{ __( 'Select all', 'woocommerce-gateway-stripe' ) }
	</Button>;
};

const AddPaymentMethodsTask = () => {
	const availablePaymentMethods = useGetAvailablePaymentMethodIds();
	const { isActive } = useContext( WizardTaskContext );

	// I am using internal state in this component
	// and committing the changes on `initialEnabledPaymentMethodIds` only when the "continue" button is clicked.
	// Otherwise a user could navigate to another page via soft-routing and the settings would be in un-saved state,
	// possibly causing errors.
	const [
		paymentMethodsState,
		handlePaymentMethodChange,
	] = usePaymentMethodsCheckboxState();

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
								"all available payment methods. We'll only show your customers the most relevant payment " +
								'methods based on their location and purchasing history. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
							'woocommerce-gateway-stripe'
						),
						components: {
							learnMoreLink: <ExternalLink href="TODO?" />,
						},
					} ) }
				</p>
				<Card className="add-payment-methods-task__payment-selector-wrapper">
					<CardBody>
						<div className="add-payment-methods-task__payment-selector-header">
							{ /* eslint-disable-next-line max-len */ }
							<p className="add-payment-methods-task__payment-selector-title wcpay-wizard-task__description-element is-headline">
								{ __(
									'Payments accepted at checkout',
									'woocommerce-gateway-stripe'
								) }
							</p>
							<SelectAllButton methods={ paymentMethodsState } setMethodState={ handlePaymentMethodChange } />
						</div>
						<LoadableBlock numLines={ 10 } isLoading={ ! isActive }>
							<LoadableSettingsSection numLines={ 10 }>
								<PaymentMethodCheckboxes>
									{ upeMethods.map(
										( key ) =>
											availablePaymentMethods.includes(
												key
											) && (
												<PaymentMethodCheckbox
													key={ key }
													checked={
														paymentMethodsState[
															key
														]
													}
													onChange={
														handlePaymentMethodChange
													}
													name={ key }
												/>
											)
									) }
								</PaymentMethodCheckboxes>
							</LoadableSettingsSection>
						</LoadableBlock>
					</CardBody>
				</Card>
				<LoadableBlock numLines={ 10 } isLoading={ ! isActive }>
					<ContinueButton
						paymentMethodsState={ paymentMethodsState }
					/>
				</LoadableBlock>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default AddPaymentMethodsTask;
