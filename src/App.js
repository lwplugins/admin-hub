/**
 * WordPress dependencies
 */
import { useMemo, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import Footer from './components/Footer';
import LoadError from './components/LoadError';
import Notices from './components/Notices';
import PluginTable from './components/PluginTable';
import TableSkeleton from './components/TableSkeleton';
import Toolbar from './components/Toolbar';
import TopBar from './components/TopBar';
import { countByStatus, visibleRows } from './data/filters';
import usePlugins from './data/usePlugins';

/**
 * The LW Plugins overview: every registry plugin with its state.
 */
export default function App() {
	const { plugins, error, reload, activate, busy } = usePlugins();
	const [ search, setSearch ] = useState( '' );
	const [ status, setStatus ] = useState( 'all' );

	const counts = useMemo(
		() => ( plugins ? countByStatus( plugins ) : null ),
		[ plugins ]
	);
	const rows = useMemo(
		() => ( plugins ? visibleRows( plugins, status, search ) : [] ),
		[ plugins, status, search ]
	);

	let content;
	if ( error ) {
		content = <LoadError message={ error } onRetry={ reload } />;
	} else if ( ! plugins ) {
		content = <TableSkeleton />;
	} else {
		content = (
			<>
				<Toolbar
					search={ search }
					onSearch={ setSearch }
					status={ status }
					onStatus={ setStatus }
					counts={ counts }
				/>
				<PluginTable
					rows={ rows }
					search={ search }
					busy={ busy }
					onActivate={ activate }
					onReset={ () => {
						setSearch( '' );
						setStatus( 'all' );
					} }
				/>
			</>
		);
	}

	return (
		<>
			<div className="lw-hub-shell">
				<TopBar counts={ counts } />
				<main className="lw-hub-content">{ content }</main>
				<Footer />
			</div>
			<Notices />
		</>
	);
}
