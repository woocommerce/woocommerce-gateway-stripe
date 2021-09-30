import styled from '@emotion/styled';
import { CardFooter } from '@wordpress/components';

export default styled( CardFooter )`
	// increasing the specificity of the styles to override the Gutenberg ones
	&.is-size-medium.is-size-medium {
		padding: 24px;
	}
`;
