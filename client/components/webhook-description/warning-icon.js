import React from 'react';
import GridIcon from 'gridicons';

const WarningIcon = () => {
	return (
		<span data-testid="warning-icon">
			<GridIcon
				icon="notice-outline"
				size={ 24 }
				style={ {
					marginRight: '0.6rem',
					fill: '#674600',
				} }
			/>
		</span>
	);
};

export default WarningIcon;
