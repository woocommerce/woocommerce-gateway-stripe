import './style.scss';

/**
 * Renders placeholder while data are being loaded.
 *
 * @param {Object} props Component props.
 * @param {boolean} props.isLoading Flag used to display placeholder or content.
 * @param {string} props.display Defines how the placeholder is displayed: inline-block (default), inline or block.
 * @param {JSX.Element} [props.placeholder] Custom placeholder content.
 * @param {JSX.Element} [props.value] Content rendered when data are loaded. Has lower priority than `children`.
 * @param {JSX.Element} [props.children] Content rendered when data are loaded. Has higher priority than `value`.
 *
 * @return {JSX.Element} Loadable content
 */
const Loadable = ( { isLoading, display, placeholder, value, children } ) =>
	isLoading ? (
		<span
			className={
				display
					? `is-loadable-placeholder is-${ display }`
					: 'is-loadable-placeholder'
			}
			aria-busy="true"
		>
			{ undefined === placeholder ? children || value : placeholder }
		</span>
	) : (
		children || value || null
	);

/**
 * Helper component for rendering loadable block which takes several lines in the ui.
 *
 * @param {Object} props Component props.
 * @param {number} props.numLines Vertical size of the component in lines.
 *
 * @return {JSX.Element} Loadable content
 */
export const LoadableBlock = ( { numLines = 1, ...loadableProps } ) => {
	const placeholder = (
		<p style={ { lineHeight: numLines } }>Block placeholder</p>
	);
	return (
		<Loadable
			{ ...loadableProps }
			placeholder={ placeholder }
			display="block"
		/>
	);
};

export default Loadable;
