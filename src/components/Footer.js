/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import LwPluginsWordmark from './LwPluginsWordmark';

/**
 * Footer: GitHub link left, LW Plugins wordmark (lwplugins.com) right.
 */
export default function Footer() {
	return (
		<footer className="lw-hub-footer">
			<a
				className="lw-hub-footer__link"
				href="https://github.com/lwplugins"
				target="_blank"
				rel="noopener noreferrer"
			>
				{ __( 'All LW plugins on GitHub', 'lw-admin-hub' ) }
				<span className="screen-reader-text">
					{ __( '(opens in a new tab)', 'lw-admin-hub' ) }
				</span>
			</a>
			<a
				className="lw-hub-footer__brand"
				href="https://lwplugins.com"
				target="_blank"
				rel="noopener noreferrer"
			>
				<LwPluginsWordmark />
				<span className="screen-reader-text">
					{ __( 'LW Plugins (opens in a new tab)', 'lw-admin-hub' ) }
				</span>
			</a>
		</footer>
	);
}
