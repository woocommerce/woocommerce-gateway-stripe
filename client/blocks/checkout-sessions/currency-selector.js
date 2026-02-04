import { CurrencySelectorElement } from '@stripe/react-stripe-js/checkout';
import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import './currency-selector.scss';

const CurrencySelector = ( { mode = 'after-payment-method' } ) => {
	const currencySelectorContainerRef = useRef( null );
	const currencySelectorRef = useRef( null );
	const currencySelectorUnavailableRef = useRef( null );

	useEffect( () => {
		if ( currencySelectorContainerRef.current ) {
			return;
		}

		if ( mode !== 'after-payment-method' ) {
			return;
		}

		const paymentMethodElement =
			document.getElementById( 'payment-method' );
		if ( ! paymentMethodElement ) {
			return;
		}

		const existingCurrencySelectorContainer = document.getElementById(
			'wc-stripe-checkout-currency-selector-container'
		);
		if ( existingCurrencySelectorContainer ) {
			currencySelectorContainerRef.current =
				existingCurrencySelectorContainer;
		} else {
			const currencySelectorContainer = document.createElement( 'div' );
			currencySelectorContainer.id =
				'wc-stripe-checkout-currency-selector-container';
			paymentMethodElement.insertAdjacentElement(
				'afterend',
				currencySelectorContainer
			);
			currencySelectorContainerRef.current = currencySelectorContainer;
		}

		const existingSelectorElement = document.getElementById(
			'wc-stripe-checkout-currency-selector'
		);
		if ( existingSelectorElement ) {
			currencySelectorRef.current = existingSelectorElement;
		} else {
			const currencySelectorElement = document.createElement( 'div' );
			currencySelectorElement.id = 'wc-stripe-checkout-currency-selector';
			currencySelectorContainerRef.current.appendChild(
				currencySelectorElement
			);
			currencySelectorRef.current = currencySelectorElement;
		}

		const existingCurrencySelectorUnavailableElement =
			document.getElementById(
				'wc-stripe-checkout-currency-selector-unavailable'
			);
		if ( existingCurrencySelectorUnavailableElement ) {
			currencySelectorUnavailableRef.current =
				existingCurrencySelectorUnavailableElement;
			existingCurrencySelectorUnavailableElement.style.display = 'none';
		} else {
			const currencySelectorUnavailableElement =
				document.createElement( 'div' );
			currencySelectorUnavailableElement.id =
				'wc-stripe-checkout-currency-selector-unavailable';
			currencySelectorUnavailableElement.innerText =
				'Currency selection is only available for some payment methods. Please pick a different payment method to access currency selection.';
			currencySelectorUnavailableElement.style.display = 'none';
			currencySelectorContainerRef.current.appendChild(
				currencySelectorUnavailableElement
			);
			currencySelectorUnavailableRef.current =
				currencySelectorUnavailableElement;
		}

		return () => {
			if ( currencySelectorUnavailableRef.current ) {
				currencySelectorUnavailableRef.current.style.display = '';
			}
		};
	}, [
		currencySelectorContainerRef,
		currencySelectorRef,
		currencySelectorUnavailableRef,
		mode,
	] );

	if ( mode !== 'after-payment-method' ) {
		return <CurrencySelectorElement />;
	}

	if ( ! currencySelectorRef.current ) {
		return null;
	}

	const currencySelectorContent = (
		<>
			<div className="wc-stripe-checkout-currency-selector-title">
				Select your preferred payment currency
			</div>
			<CurrencySelectorElement />
		</>
	);

	return (
		<>
			{ createPortal(
				currencySelectorContent,
				currencySelectorRef.current
			) }
		</>
	);
};

export default CurrencySelector;
