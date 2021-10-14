import React, { useEffect, useRef, useState, memo } from 'react';
import { createPortal } from 'react-dom';
import classNames from 'classnames';
import { debounce, noop } from 'lodash';

import './style.scss';

const rootElement =
	document.getElementById( 'wpbody-content' ) || document.body;

const isEventTriggeredWithin = ( event, element ) =>
	element && ( element === event.target || element.contains( event.target ) );

const useHideDelay = (
	isVisibleProp,
	{ hideDelayMs = 600, triggerRef, popoverRef, onHide = noop }
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
			rootElement.dispatchEvent( new Event( 'wcstripe-popover-open' ) );
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

		// immediately hide this popover if another one opens
		const handleHideElement = () => {
			setIsVisible( false );
			onHideCallbackRef.current();
		};

		// do not hide the popover if a click event has occurred and the click happened within the popover or within the wrapped element
		const handleDocumentClick = ( event ) => {
			if (
				isEventTriggeredWithin(
					event,
					triggerRef.current?.firstChild
				) ||
				isEventTriggeredWithin( event, popoverRef.current )
			) {
				return;
			}

			setIsVisible( false );
			onHideCallbackRef.current();
		};

		document.addEventListener( 'click', handleDocumentClick );
		rootElement.addEventListener(
			'wcstripe-popover-open',
			handleHideElement
		);

		return () => {
			document.removeEventListener( 'click', handleDocumentClick );
			rootElement.removeEventListener(
				'wcstripe-popover-open',
				handleHideElement
			);
		};
	}, [ isVisibleProp, isVisible, triggerRef, popoverRef ] );

	return isVisible;
};

const PopoverPortal = memo( ( { children } ) => {
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

const PopoverBase = ( {
	className,
	children,
	content,
	hideDelayMs,
	isVisible,
	onHide,
	maxWidth = '250px',
} ) => {
	const wrapperRef = useRef( null );
	const popoverWrapperRef = useRef( null );

	// using a delayed hide, to allow the fade-out animation to complete
	const isPopoverVisible = useHideDelay( isVisible, {
		hideDelayMs,
		triggerRef: wrapperRef,
		popoverRef: popoverWrapperRef,
		onHide,
	} );

	useEffect( () => {
		const calculatePopoverPosition = () => {
			// calculate the position of the popover based on the wrapper's bounding rect
			if ( ! isPopoverVisible ) {
				return;
			}

			const popoverElement = popoverWrapperRef.current;
			const wrappedElement = wrapperRef.current?.firstChild;
			if ( ! popoverElement || ! wrappedElement ) {
				return;
			}

			popoverElement.style.maxWidth = maxWidth;

			const wrappedElementRect = wrappedElement.getBoundingClientRect();
			const popoverElementRect = popoverElement.getBoundingClientRect();

			const popoverHeight = popoverElementRect.height;
			popoverElement.style.top = `${
				wrappedElementRect.top - popoverHeight - 8
			}px`;
			const elementMiddle =
				wrappedElement.offsetWidth / 2 + wrappedElementRect.left;
			const popoverWidth = popoverElement.offsetWidth;
			popoverElement.style.left = `${
				elementMiddle - popoverWidth / 2
			}px`;

			// make it visible only after all the calculations are done.
			popoverElement.style.visibility = 'visible';
			popoverElement.style.opacity = 1;
		};

		calculatePopoverPosition();

		const debouncedCalculation = debounce( calculatePopoverPosition, 150 );

		window.addEventListener( 'resize', debouncedCalculation );
		document.addEventListener( 'scroll', debouncedCalculation );

		return () => {
			window.removeEventListener( 'resize', debouncedCalculation );
			document.removeEventListener( 'scroll', debouncedCalculation );
		};
	}, [ isPopoverVisible, maxWidth ] );

	return (
		<>
			<div
				className="wcstripe-popover__content-wrapper"
				ref={ wrapperRef }
			>
				{ children }
			</div>
			{ isPopoverVisible && (
				<PopoverPortal>
					<div
						ref={ popoverWrapperRef }
						className={ classNames(
							'wcstripe-popover__popover-wrapper',
							{ 'is-hiding': ! isVisible }
						) }
					>
						<div
							className={ classNames(
								'wcstripe-popover__popover',
								className
							) }
						>
							{ content }
						</div>
					</div>
				</PopoverPortal>
			) }
		</>
	);
};

export default PopoverBase;
