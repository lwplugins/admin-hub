/**
 * Internal dependencies
 */
import { SkeletonBlock, SkeletonRegion, SkeletonText } from './skeleton';

const ROWS = 8;

/**
 * Placeholder toolbar + table while the list loads: same boxes as the real
 * layout, so nothing jumps when data arrives.
 */
export default function TableSkeleton() {
	return (
		<SkeletonRegion className="lw-hub-skeleton">
			<span className="lw-hub-toolbar">
				<SkeletonBlock width={ 260 } height={ 40 } />
				<span className="lw-hub-chips">
					{ [ 64, 84, 96, 120 ].map( ( width ) => (
						<SkeletonBlock
							key={ width }
							width={ width }
							height={ 32 }
							round
						/>
					) ) }
				</span>
			</span>
			<span className="lw-hub-tablewrap lw-hub-skeleton__table">
				{ Array.from( { length: ROWS }, ( _, index ) => (
					<span key={ index } className="lw-hub-skeleton__row">
						<SkeletonBlock width={ 36 } height={ 36 } />
						<span className="lw-hub-skeleton__text">
							<SkeletonText
								width={ `${ 28 + ( ( index * 7 ) % 14 ) }%` }
								size="lg"
							/>
							<SkeletonText
								width={ `${ 50 + ( ( index * 11 ) % 30 ) }%` }
								size="sm"
							/>
						</span>
						<SkeletonBlock width={ 72 } height={ 22 } round />
						<SkeletonBlock width={ 48 } height={ 14 } />
						<SkeletonBlock width={ 92 } height={ 40 } />
					</span>
				) ) }
			</span>
		</SkeletonRegion>
	);
}
