/**
 * Internal dependencies
 */
import { statusLabel } from '../data/filters';

/**
 * Status badge: Active / Inactive / Not installed.
 *
 * @param {Object} props
 * @param {string} props.status active|inactive|missing.
 */
export default function StatusPill( { status } ) {
	return (
		<span className={ `lw-hub-pill is-${ status }` }>
			{ statusLabel( status ) }
		</span>
	);
}
