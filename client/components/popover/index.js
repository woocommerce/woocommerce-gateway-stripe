import React, { useState } from 'react';
import { Popover as PopoverComponent } from '@wordpress/components';
import './style.scss';

const Popover = ( { content, BaseComponent } ) => {
	const [ isVisible, setIsVisible ] = useState( false );

	const toggleVisible = () => {
		setIsVisible( ( state ) => ! state );
	};

	return (
		<BaseComponent onClick={ toggleVisible }>
			{ isVisible && (
				<PopoverComponent
					className="wc-stripe-popover"
					animate={ true }
					placement="top"
					variant="toolbar"
					onFocusOutside={ () => setIsVisible( false ) }
				>
					{ content }
				</PopoverComponent>
			) }
		</BaseComponent>
	);
};

export default Popover;
