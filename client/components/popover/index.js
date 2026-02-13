import React, { useState } from 'react';
import styled from '@emotion/styled';
import { Popover as PopoverComponent } from '@wordpress/components';

const StyledPopover = styled( PopoverComponent )`
	top: -10px !important;

	.components-popover__content {
		border: 1px solid #cccccc;
		border-radius: 2px;
		box-shadow: 0px 2px 6px 0px rgba( 0, 0, 0, 0.05 );
		padding: 12px;
	}

	@media ( min-width: 660px ) {
		.components-popover__content {
			width: 250px;
		}
	}
`;

const Popover = ( { content, BaseComponent } ) => {
	const [ isVisible, setIsVisible ] = useState( false );

	const toggleVisible = () => {
		setIsVisible( ( state ) => ! state );
	};

	return (
		<BaseComponent onClick={ toggleVisible }>
			{ isVisible && (
				<StyledPopover
					animate={ true }
					placement="top"
					variant="toolbar"
					onFocusOutside={ () => setIsVisible( false ) }
				>
					{ content }
				</StyledPopover>
			) }
		</BaseComponent>
	);
};

export default Popover;
