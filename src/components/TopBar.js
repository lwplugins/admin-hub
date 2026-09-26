/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import BoltMark from './BoltMark';

/**
 * Brand + page title left, status summary right.
 *
 * @param {Object}      props
 * @param {Object|null} props.counts Rows per status, null while loading.
 */
export default function TopBar( { counts } ) {
	return (
		<header className="lw-hub-topbar">
			<h1 className="lw-hub-topbar__title">
				<BoltMark />
				{ __( 'LW Plugins', 'lw-admin-hub' ) }
			</h1>
			{ counts && (
				<p className="lw-hub-summary">
					<span>
						<b>{ counts.active }</b>{ ' ' }
						{ _n(
							'active',
							'active',
							counts.active,
							'lw-admin-hub'
						) }
					</span>
					<span>
						<b>{ counts.inactive }</b>{ ' ' }
						{ _n(
							'inactive',
							'inactive',
							counts.inactive,
							'lw-admin-hub'
						) }
					</span>
					<span>
						<b>{ counts.missing }</b>{ ' ' }
						{ _n(
							'not installed',
							'not installed',
							counts.missing,
							'lw-admin-hub'
						) }
					</span>
					<span className="screen-reader-text">
						{ sprintf(
							/* translators: %d: number of LW plugins listed. */
							_n(
								'%d LW plugin listed.',
								'%d LW plugins listed.',
								counts.all,
								'lw-admin-hub'
							),
							counts.all
						) }
					</span>
				</p>
			) }
		</header>
	);
}
