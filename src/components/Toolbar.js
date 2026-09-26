/**
 * WordPress dependencies
 */
import { SearchControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { statusFilters } from '../data/filters';

/**
 * Search field + status filter chips (toggle buttons, one pressed).
 *
 * @param {Object}                  props
 * @param {string}                  props.search   Search text.
 * @param {(value: string) => void} props.onSearch Search change.
 * @param {string}                  props.status   Pressed chip.
 * @param {(key: string) => void}   props.onStatus Chip change.
 * @param {Object}                  props.counts   Rows per status.
 */
export default function Toolbar( {
	search,
	onSearch,
	status,
	onStatus,
	counts,
} ) {
	return (
		<div className="lw-hub-toolbar">
			<SearchControl
				__nextHasNoMarginBottom
				className="lw-hub-search"
				label={ __( 'Search plugins', 'lw-admin-hub' ) }
				placeholder={ __( 'Search plugins', 'lw-admin-hub' ) }
				value={ search }
				onChange={ onSearch }
			/>
			<div
				className="lw-hub-chips"
				role="group"
				aria-label={ __( 'Filter by status', 'lw-admin-hub' ) }
			>
				{ statusFilters().map( ( chip ) => (
					<button
						key={ chip.key }
						type="button"
						className="lw-hub-chip"
						aria-pressed={ status === chip.key }
						onClick={ () => onStatus( chip.key ) }
					>
						{ chip.label }
						<span className="lw-hub-chip__count">
							{ counts[ chip.key ] || 0 }
						</span>
					</button>
				) ) }
			</div>
		</div>
	);
}
