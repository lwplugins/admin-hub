/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import { api, errorMessage } from './api';

/**
 * The plugin rows, their load state and the activate action.
 *
 * @return {Object} { plugins, error, reload, activate, busy }
 */
export default function usePlugins() {
	const [ plugins, setPlugins ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( '' );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const reload = useCallback( () => {
		setError( '' );
		setPlugins( null );
		api.list()
			.then( ( response ) => setPlugins( response.plugins || [] ) )
			.catch( ( failure ) => setError( errorMessage( failure ) ) );
	}, [] );

	useEffect( reload, [ reload ] );

	const activate = useCallback(
		( plugin ) => {
			setBusy( plugin.slug );
			api.activate( plugin.slug )
				.then( ( response ) => {
					setPlugins( ( rows ) =>
						rows.map( ( row ) =>
							row.slug === plugin.slug ? response.plugin : row
						)
					);
					createSuccessNotice(
						sprintf(
							/* translators: %s: plugin name. */
							__( '%s is active.', 'lw-admin-hub' ),
							plugin.name
						),
						{
							type: 'snackbar',
							actions: [
								{
									label: __( 'Reload page', 'lw-admin-hub' ),
									onClick: () => window.location.reload(),
								},
							],
						}
					);
				} )
				.catch( ( failure ) =>
					createErrorNotice( errorMessage( failure ), {
						type: 'snackbar',
					} )
				)
				.finally( () => setBusy( '' ) );
		},
		[ createSuccessNotice, createErrorNotice ]
	);

	return { plugins, error, reload, activate, busy };
}
