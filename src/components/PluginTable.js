/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import PluginIcon from './PluginIcon';
import RowAction from './RowAction';
import StatusPill from './StatusPill';

/**
 * The plugin table. Below 600px each row becomes a card (CSS only).
 *
 * @param {Object}                   props
 * @param {Array}                    props.rows       Visible rows.
 * @param {string}                   props.search     Search text (for the empty state).
 * @param {string}                   props.busy       Slug being activated.
 * @param {(plugin: Object) => void} props.onActivate Activate callback.
 * @param {() => void}               props.onReset    Clear search and filter.
 */
export default function PluginTable( {
	rows,
	search,
	busy,
	onActivate,
	onReset,
} ) {
	return (
		<div className="lw-hub-tablewrap">
			<table className="lw-hub-table">
				<thead>
					<tr>
						<th scope="col">{ __( 'Plugin', 'lw-admin-hub' ) }</th>
						<th scope="col">{ __( 'Status', 'lw-admin-hub' ) }</th>
						<th scope="col">{ __( 'Version', 'lw-admin-hub' ) }</th>
						<th scope="col">
							<span className="screen-reader-text">
								{ __( 'Action', 'lw-admin-hub' ) }
							</span>
						</th>
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( plugin ) => (
						<tr key={ plugin.slug }>
							<td className="lw-hub-table__plugin">
								<div className="lw-hub-name">
									<PluginIcon plugin={ plugin } />
									<span>
										<strong>
											{ plugin.name }
											{ plugin.beta && (
												<span className="lw-hub-beta">
													{ __(
														'Beta',
														'lw-admin-hub'
													) }
												</span>
											) }
										</strong>
										<small>{ plugin.description }</small>
									</span>
								</div>
							</td>
							<td className="lw-hub-table__status">
								<StatusPill status={ plugin.status } />
							</td>
							<td className="lw-hub-table__version">
								{ plugin.version ? (
									`v${ plugin.version }`
								) : (
									<span
										aria-label={ __(
											'No version',
											'lw-admin-hub'
										) }
									>
										–
									</span>
								) }
							</td>
							<td className="lw-hub-table__action">
								<RowAction
									plugin={ plugin }
									busy={ busy === plugin.slug }
									locked={ busy !== '' }
									onActivate={ onActivate }
								/>
							</td>
						</tr>
					) ) }
					{ ! rows.length && (
						<tr className="lw-hub-table__empty">
							<td colSpan="4">
								<p>
									{ search.trim()
										? sprintf(
												/* translators: %s: search text. */
												__(
													'No plugins match “%s”.',
													'lw-admin-hub'
												),
												search.trim()
											)
										: __(
												'No plugins in this group.',
												'lw-admin-hub'
											) }
								</p>
								<Button variant="link" onClick={ onReset }>
									{ __( 'Show all plugins', 'lw-admin-hub' ) }
								</Button>
							</td>
						</tr>
					) }
				</tbody>
			</table>
		</div>
	);
}
