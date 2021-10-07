import React from 'react';
import { Icon } from '@wordpress/components';
import Tooltip from 'wcstripe/components/tooltip';

import './style.scss';

const MaybeAddTooltip = ( { children, tooltip } ) =>
	tooltip ? <Tooltip content={ tooltip }>{ children }</Tooltip> : children;

const SectionStatusEnabled = ( { children, tooltip } ) => {
	return (
		<span className="section-status__info--green">
			<MaybeAddTooltip tooltip={ tooltip }>
				<Icon icon="yes-alt" />
				<span className="section-status__info-text">{ children }</span>
			</MaybeAddTooltip>
		</span>
	);
};

const SectionStatusDisabled = ( { children, tooltip } ) => {
	return (
		<span className="section-status__info--yellow">
			<MaybeAddTooltip tooltip={ tooltip }>
				<Icon icon="warning" />
				<span className="section-status__info-text">{ children }</span>
			</MaybeAddTooltip>
		</span>
	);
};

const SectionStatus = ( { isEnabled, children, tooltip } ) => {
	return isEnabled ? (
		<SectionStatusEnabled tooltip={ tooltip }>
			{ children }
		</SectionStatusEnabled>
	) : (
		<SectionStatusDisabled tooltip={ tooltip }>
			{ children }
		</SectionStatusDisabled>
	);
};

export default SectionStatus;
