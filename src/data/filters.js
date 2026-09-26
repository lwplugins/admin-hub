/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Status filter chips, in display order.
 *
 * @return {Array<{key: string, label: string}>} Chips.
 */
export const statusFilters = () => [
	{ key: 'all', label: __( 'All', 'lw-admin-hub' ) },
	{ key: 'active', label: __( 'Active', 'lw-admin-hub' ) },
	{ key: 'inactive', label: __( 'Inactive', 'lw-admin-hub' ) },
	{ key: 'missing', label: __( 'Not installed', 'lw-admin-hub' ) },
];

/**
 * Human label of a row status.
 *
 * @param {string} status active|inactive|missing.
 * @return {string} Label.
 */
export const statusLabel = ( status ) =>
	statusFilters().find( ( item ) => item.key === status )?.label || status;

/**
 * Rows matching the chip and the search text (name, description, slug).
 *
 * @param {Array}  plugins Rows.
 * @param {string} status  Chip key.
 * @param {string} search  Search text.
 * @return {Array} Visible rows.
 */
export const visibleRows = ( plugins, status, search ) => {
	const needle = search.trim().toLowerCase();

	return plugins.filter(
		( plugin ) =>
			( status === 'all' || plugin.status === status ) &&
			( ! needle ||
				`${ plugin.name } ${ plugin.description } ${ plugin.slug }`
					.toLowerCase()
					.includes( needle ) )
	);
};

/**
 * Row count per status.
 *
 * @param {Array} plugins Rows.
 * @return {Object} { all, active, inactive, missing }.
 */
export const countByStatus = ( plugins ) =>
	plugins.reduce(
		( counts, plugin ) => ( {
			...counts,
			[ plugin.status ]: ( counts[ plugin.status ] || 0 ) + 1,
		} ),
		{ all: plugins.length, active: 0, inactive: 0, missing: 0 }
	);
