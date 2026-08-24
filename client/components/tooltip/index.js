import React, { Children, cloneElement, useState } from 'react';
import { noop } from 'lodash';
import TooltipBase from './tooltip-base';

const Tooltip = ( {
	isVisible,
	onHide = noop,
	asChild = false,
	children,
	...props
} ) => {
	const [ isHovered, setIsHovered ] = useState( false );
	const [ isClicked, setIsClicked ] = useState( false );

	const handleMouseEnter = () => {
		setIsHovered( true );
	};
	const handleMouseLeave = () => {
		setIsHovered( false );
		onHide();
	};
	const handleMouseClick = ( event ) => {
		if ( event.target.closest( 'a' ) ) {
			return;
		}
		event.preventDefault();
		setIsClicked( ( val ) => ! val );
		if ( isClicked ) {
			onHide();
		}
	};
	const handleHide = () => {
		setIsHovered( false );
		setIsClicked( false );
		onHide();
	};
	const tooltipBase = ( trigger ) => (
		<TooltipBase
			{ ...props }
			onHide={ handleHide }
			isVisible={ isVisible || isHovered || isClicked }
		>
			{ trigger }
		</TooltipBase>
	);

	if ( asChild ) {
		const child = Children.only( children );
		return tooltipBase(
			cloneElement( child, {
				onMouseEnter: ( event ) => {
					child.props.onMouseEnter?.( event );
					handleMouseEnter();
				},
				onMouseLeave: ( event ) => {
					child.props.onMouseLeave?.( event );
					handleMouseLeave();
				},
				onFocus: ( event ) => {
					child.props.onFocus?.( event );
					handleMouseEnter();
				},
				onBlur: ( event ) => {
					child.props.onBlur?.( event );
					handleMouseLeave();
				},
				onClick: ( event ) => {
					child.props.onClick?.( event );
					if ( ! event.defaultPrevented ) {
						handleMouseClick( event );
					}
				},
			} )
		);
	}

	return (
		<button
			className="wcstripe-tooltip__content-wrapper"
			// on touch devices there's no mouse enter/leave, so we need to use a separate event (click/focus)
			// this creates 2 different (desirable) states on non-touch devices: if you hover and then click, the tooltip will persist
			onMouseEnter={ handleMouseEnter }
			onMouseLeave={ handleMouseLeave }
			onFocus={ handleMouseEnter }
			onBlur={ handleMouseLeave }
			onClick={ handleMouseClick }
		>
			{ tooltipBase( children ) }
		</button>
	);
};

export default Tooltip;
