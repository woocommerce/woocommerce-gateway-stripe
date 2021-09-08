/**
 * External dependencies
 */
import React, { useEffect, useRef, useState, memo } from 'react';
import { createPortal } from 'react-dom';
import classNames from 'classnames';
import { debounce, noop } from 'lodash';

/**
 * Internal dependencies
 */
import './style.scss';

const rootElement =
	document.getElementById( 'wpbody-content' ) || document.body;

const isEventTriggeredWithin = ( event, element ) =>
	element && ( element === event.target || element.contains( event.target ) );

const useHideDelay = (
	isVisibleProp,
	{ hideDelayMs = 600, triggerRef, tooltipRef, onHide = noop }
) => {
	const [ isVisible, setIsVisible ] = useState( isVisibleProp );
	// not using state for this, we don't need to cause a re-render
	const hasMountedRef = useRef( false );
	const onHideCallbackRef = useRef( onHide );

	useEffect( () => {
		onHideCallbackRef.current = onHide;
	}, [ onHide ] );

	// hide delay
	useEffect( () => {
		let timer = null;

		if ( ! hasMountedRef.current ) {
			hasMountedRef.current = true;
			return;
		}

		// element is marked as visible, no need to hide it
		if ( isVisibleProp ) {
			rootElement.dispatchEvent( new Event( 'wcpay-tooltip-open' ) );
			setIsVisible( true );
			return;
		}

		if ( ! isVisible ) {
			return;
		}

		// element is marked as not visible, hide it after `hideDelayMs` milliseconds
		timer = setTimeout( () => {
			setIsVisible( false );
			onHideCallbackRef.current();
		}, hideDelayMs );

		return () => {
			clearTimeout( timer );
		};
	}, [ setIsVisible, hideDelayMs, isVisibleProp, isVisible ] );

	// listen to other events to hide
	useEffect( () => {
		if ( ! isVisible ) return;

		// immediately hide this tooltip if another one opens
		const handleHideElement = () => {
			setIsVisible( false );
			onHideCallbackRef.current();
		};

		// do not hide the tooltip if a click event has occurred and the click happened within the tooltip or within the wrapped element
		const handleDocumentClick = ( event ) => {
			if (
				isEventTriggeredWithin(
					event,
					triggerRef.current?.firstChild
				) ||
				isEventTriggeredWithin( event, tooltipRef.current )
			) {
				return;
			}

			setIsVisible( false );
			onHideCallbackRef.current();
		};

		document.addEventListener( 'click', handleDocumentClick );
		rootElement.addEventListener( 'wcpay-tooltip-open', handleHideElement );

		return () => {
			document.removeEventListener( 'click', handleDocumentClick );
			rootElement.removeEventListener(
				'wcpay-tooltip-open',
				handleHideElement
			);
		};
	}, [ isVisibleProp, isVisible, triggerRef, tooltipRef ] );

	return isVisible;
};

const TooltipPortal = memo( ( { children } ) => {
	const node = useRef( null );
	if ( ! node.current ) {
		node.current = document.createElement( 'div' );
		rootElement.appendChild( node.current );
	}

	// on component unmount, clear any reference to the created node
	useEffect( () => {
		return () => {
			rootElement.removeChild( node.current );
			node.current = null;
		};
	}, [] );

	return createPortal( children, node.current );
} );

const TooltipBase = ( {
	className,
	children,
	content,
	hideDelayMs,
	isVisible,
	onHide,
	maxWidth = '250px',
} ) => {
	const wrapperRef = useRef( null );
	const tooltipWrapperRef = useRef( null );

	// using a delayed hide, to allow the fade-out animation to complete
	const isTooltipVisible = useHideDelay( isVisible, {
		hideDelayMs,
		triggerRef: wrapperRef,
		tooltipRef: tooltipWrapperRef,
		onHide,
	} );

	useEffect( () => {
		const calculateTooltipPosition = () => {
			// calculate the position of the tooltip based on the wrapper's bounding rect
			if ( ! isTooltipVisible ) {
				return;
			}

			const tooltipElement = tooltipWrapperRef.current;
			const wrappedElement = wrapperRef.current?.firstChild;
			if ( ! tooltipElement || ! wrappedElement ) {
				return;
			}

			tooltipElement.style.maxWidth = maxWidth;

			const wrappedElementRect = wrappedElement.getBoundingClientRect();
			const tooltipElementRect = tooltipElement.getBoundingClientRect();

			const tooltipHeight = tooltipElementRect.height;
			tooltipElement.style.top = `${
				wrappedElementRect.top - tooltipHeight - 8
			}px`;
			const elementMiddle =
				wrappedElement.offsetWidth / 2 + wrappedElementRect.left;
			const tooltipWidth = tooltipElement.offsetWidth;
			tooltipElement.style.left = `${
				elementMiddle - tooltipWidth / 2
			}px`;

			// make it visible only after all the calculations are done.
			tooltipElement.style.visibility = 'visible';
			tooltipElement.style.opacity = 1;
		};

		calculateTooltipPosition();

		const debouncedCalculation = debounce( calculateTooltipPosition, 150 );

		window.addEventListener( 'resize', debouncedCalculation );
		document.addEventListener( 'scroll', debouncedCalculation );

		return () => {
			window.removeEventListener( 'resize', debouncedCalculation );
			document.removeEventListener( 'scroll', debouncedCalculation );
		};
	}, [ isTooltipVisible, maxWidth ] );

	return (
		<>
			<div className="wcpay-tooltip__content-wrapper" ref={ wrapperRef }>
				{ children }
			</div>
			{ isTooltipVisible && (
				<TooltipPortal>
					<div
						ref={ tooltipWrapperRef }
						className={ classNames(
							'wcpay-tooltip__tooltip-wrapper',
							{ 'is-hiding': ! isVisible }
						) }
					>
						<div
							className={ classNames(
								'wcpay-tooltip__tooltip',
								className
							) }
						>
							{ content }
						</div>
					</div>
				</TooltipPortal>
			) }
		</>
	);
};

export default TooltipBase;
