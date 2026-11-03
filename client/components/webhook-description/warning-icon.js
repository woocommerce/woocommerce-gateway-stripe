import React from 'react';
import GridiconNoticeOutline from 'gridicons/dist/notice-outline';

const WarningIcon = () => {
	return (
		<span data-testid="warning-icon">
			<GridiconNoticeOutline
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
