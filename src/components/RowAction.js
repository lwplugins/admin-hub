/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, external } from '@wordpress/icons';

/**
 * Settings link (active), Activate button (installed, inactive) or GitHub
 * link (not installed). No updates, no installs.
 *
 * @param {Object}                   props
 * @param {Object}                   props.plugin     Row.
 * @param {boolean}                  props.busy       Activation of this row in progress.
 * @param {boolean}                  props.locked     Another activation is in progress.
 * @param {(plugin: Object) => void} props.onActivate Activate callback.
 */
export default function RowAction( { plugin, busy, locked, onActivate } ) {
	if ( plugin.status === 'active' ) {
		return plugin.settingsUrl ? (
			<Button
				__next40pxDefaultSize
				variant="secondary"
				href={ plugin.settingsUrl }
				aria-label={
					/* translators: %s: plugin name. */
					sprintf( __( '%s settings', 'lw-admin-hub' ), plugin.name )
				}
			>
				{ __( 'Settings', 'lw-admin-hub' ) }
			</Button>
		) : null;
	}

	if ( plugin.status === 'inactive' ) {
		return plugin.canActivate ? (
			<Button
				__next40pxDefaultSize
				variant="primary"
				isBusy={ busy }
				disabled={ busy || locked }
				accessibleWhenDisabled
				onClick={ () => onActivate( plugin ) }
				aria-label={
					/* translators: %s: plugin name. */
					sprintf( __( 'Activate %s', 'lw-admin-hub' ), plugin.name )
				}
			>
				{ busy
					? __( 'Activating…', 'lw-admin-hub' )
					: __( 'Activate', 'lw-admin-hub' ) }
			</Button>
		) : null;
	}

	return plugin.githubUrl ? (
		<Button
			__next40pxDefaultSize
			variant="tertiary"
			href={ plugin.githubUrl }
			target="_blank"
			rel="noopener noreferrer"
			className="lw-hub-github"
		>
			{ __( 'GitHub', 'lw-admin-hub' ) }
			<Icon icon={ external } size={ 16 } />
			<span className="screen-reader-text">
				{ sprintf(
					/* translators: %s: plugin name. */
					__( '%s on GitHub (opens in a new tab)', 'lw-admin-hub' ),
					plugin.name
				) }
			</span>
		</Button>
	) : null;
}
