import React, { useState } from 'react';
import { noop } from 'lodash';
import TooltipBase from './tooltip-base';

const Tooltip = ( { isVisible, onHide = noop, ...props } ) => {
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
		// Portal content (tooltip popup) is outside the button in the DOM but
		// still bubbles clicks through React's component tree. Guard against
		// treating those clicks as trigger activations.
		if ( ! event.currentTarget.contains( event.target ) ) {
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
			<TooltipBase
				{ ...props }
				onHide={ handleHide }
				isVisible={ isVisible || isHovered || isClicked }
			/>
		</button>
	);
};

export default Tooltip;
