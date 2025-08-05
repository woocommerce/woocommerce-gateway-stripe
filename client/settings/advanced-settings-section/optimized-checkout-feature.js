import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import {
	CheckboxControl,
	ExternalLink,
	TextControl,
} from '@wordpress/components';
import React, { useEffect, useRef, useState } from 'react';
import { getQuery } from '@woocommerce/navigation';
import { useIsOCEnabled, useIsUpeEnabled, useOCTitle } from '../../data';

const OptimizedCheckoutFeature = () => {
	const [ isOCEnabled, setIsOCEnabled ] = useIsOCEnabled();
	const [ OCTitle, setOCTitle ] = useOCTitle();
	const [ isUpeEnabled ] = useIsUpeEnabled();
	const headingRef = useRef( null );

	useEffect( () => {
		if ( ! isUpeEnabled ) {
			setIsOCEnabled( false );
		}
	}, [ isUpeEnabled, setIsOCEnabled ] );

	// Local state for the title input to prevent value reset during typing
	const [ localOCTitle, setLocalOCTitle ] = useState( OCTitle );

	// Update local state when store value changes
	useEffect( () => {
		setLocalOCTitle( OCTitle );
	}, [ OCTitle ] );

	useEffect( () => {
		if ( ! headingRef.current ) {
			return;
		}

		const { highlight } = getQuery();
		if ( highlight === 'enable-optimized-checkout' ) {
			headingRef.current.scrollIntoView( {
				behavior: 'smooth',
				block: 'start',
			} );
		}
	}, [ headingRef ] );

	const handleTitleChange = ( value ) => {
		setLocalOCTitle( value );
	};

	const handleTitleBlur = () => {
		const finalTitle = localOCTitle.trim() || OCTitle;
		setOCTitle( finalTitle );
		setLocalOCTitle( finalTitle );
	};

	const handleTitleKeyDown = ( event ) => {
		if ( event.key === 'Enter' ) {
			handleTitleBlur();
		}
	};

	return (
		<>
			<h4 ref={ headingRef }>
				{ __(
					'Enable Optimized Checkout Suite (recommended)',
					'woocommerce-gateway-stripe'
				) }
			</h4>
			<CheckboxControl
				data-testid="optimized-checkout-element-checkbox"
				label={ __(
					"Dynamically display the most relevant payment methods you've enabled",
					'woocommerce-gateway-stripe'
				) }
				help={ createInterpolateElement(
					__(
						"Stripe's Optimized Checkout Suite uses AI models to order the most relevant payment methods you've enabled for each of your customers dynamically. <learnMoreLink>Learn more</learnMoreLink>.",
						'woocommerce-gateway-stripe'
					),
					{
						learnMoreLink: (
							<ExternalLink href="https://woocommerce.com/document/stripe/setup-and-configuration/settings-guide/#advanced-settings" />
						),
					}
				) }
				checked={ isOCEnabled }
				onChange={ setIsOCEnabled }
				disabled={ ! isUpeEnabled }
			/>
			{ isOCEnabled && (
				<TextControl
					help={ __(
						'This will appear as the title of the Optimized Checkout Suite payment element on checkout.',
						'woocommerce-gateway-stripe'
					) }
					label={ __( 'Title', 'woocommerce-gateway-stripe' ) }
					value={ localOCTitle }
					onChange={ handleTitleChange }
					onBlur={ handleTitleBlur }
					onKeyDown={ handleTitleKeyDown }
				/>
			) }
		</>
	);
};

export default OptimizedCheckoutFeature;
