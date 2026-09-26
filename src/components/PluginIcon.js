/**
 * WordPress dependencies
 */
import { Icon, plugins as pluginsIcon } from '@wordpress/icons';

/**
 * The plugin's own bundled icon, or a neutral plugin glyph when the hub has
 * no icon for it (a registry entry newer than this copy).
 *
 * @param {Object} props
 * @param {Object} props.plugin Row.
 */
export default function PluginIcon( { plugin } ) {
	return (
		<span className="lw-hub-icon" aria-hidden="true">
			{ plugin.iconUrl ? (
				<img src={ plugin.iconUrl } alt="" width="22" height="22" />
			) : (
				<Icon icon={ pluginsIcon } size={ 22 } />
			) }
		</span>
	);
}
