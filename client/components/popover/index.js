import React, { useState } from 'react';
import { noop } from 'lodash';
import PopoverBase from './popover-base';

const Popover = ( { isVisible, onHide = noop, ...props } ) => {
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
			className="wcstripe-popover__content-wrapper"
			// on touch devices there's no mouse enter/leave, so we need to use a separate event (click/focus)
			// this creates 2 different (desirable) states on non-touch devices: if you hover and then click, the popover will persist
			onMouseEnter={ handleMouseEnter }
			onMouseLeave={ handleMouseLeave }
			onFocus={ handleMouseEnter }
			onBlur={ handleMouseLeave }
			onClick={ handleMouseClick }
		>
			<PopoverBase
				{ ...props }
				onHide={ handleHide }
				isVisible={ isVisible || isHovered || isClicked }
			/>
		</button>
	);
};

export default Popover;
