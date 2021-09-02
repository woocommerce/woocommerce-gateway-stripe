/**
 * Internal dependencies
 */
import './style.scss';

const types = [ 'primary', 'light', 'warning', 'alert' ];

const Chip = ( props ) => {
	const { message, type, isCompact } = props;

	const classNames = [
		'chip',
		`chip-${ types.find( ( t ) => t === type ) || 'primary' }`,
		isCompact ? 'is-compact' : '',
	];

	return <span className={ classNames.join( ' ' ).trim() }>{ message }</span>;
};

export default Chip;
