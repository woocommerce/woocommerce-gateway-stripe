/**
 * External dependencies
 */
import React from 'react';
import { css } from '@linaria/core';
import { CardBody } from '@wordpress/components';
import classNames from 'classnames';

const CardBodyStyles = css`
	// increasing the specificity of the styles to override the Gutenberg ones
	&.is-size-medium.is-size-medium {
		padding: $grid-unit-30;
	}

	h4 {
		margin-top: 0;
		margin-bottom: 1em;
	}

	> * {
		margin-top: 0;
		margin-bottom: 1em;

		// fixing the spacing on the inputs and their help text, to ensure it is consistent
		&:last-child {
			margin-bottom: 0;

			> :last-child {
				margin-bottom: 0;
			}
		}
	}

	input,
	select {
		margin: 0;
	}

	// spacing adjustment on "Express checkouts > Show express checkouts on" list
	ul > li:last-child {
		margin-bottom: 0;

		.components-base-control__field {
			margin-bottom: 0;
		}
	}

	// spacing in the "Express checkouts" settings page
	.components-radio-control__option {
		margin-bottom: 8px;
	}

	.components-base-control__help {
		margin-top: unset;
	}
`;

export default ( { className, ...restProps } ) => (
	<CardBody
		{ ...restProps }
		className={ classNames( CardBodyStyles, className ) }
	/>
);
