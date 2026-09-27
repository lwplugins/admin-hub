<?php
/**
 * Plugin-first, format-preserving merge of hub entries into a host PO/POT.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tools\Sync;

/**
 * The host file is never re-rendered: its header, wrapping, references and
 * flags stay byte for byte. Only two edits are made:
 *
 * - a hub entry whose msgid the host lacks is added (before any obsolete #~
 *   entries, else at the end), as the hub wrote it; when the host has that
 *   msgid only as an obsolete entry, that entry is revived in place (the
 *   host's translation with the hub's comments), since msgfmt rejects a live
 *   and an obsolete entry with the same msgid;
 * - a host entry with an empty msgstr gets the hub's msgstr lines, when the
 *   hub has a (non-fuzzy) translation.
 *
 * A host translation always wins, so re-running is a no-op.
 */
final class PoMerger {

	/**
	 * Merged content.
	 *
	 * @param string $host Host PO/POT content.
	 * @param string $hub  Hub PO/POT content (already rewritten to host terms).
	 * @return string
	 */
	public static function merge( string $host, string $hub ): string {
		$catalog = new PoCatalog( $host );
		$patches = array();
		$append  = array();

		foreach ( self::hub_entries( $hub ) as $entry ) {
			$own = $catalog->get( $entry['key'] );
			$old = null === $own ? $catalog->get_obsolete( $entry['key'] ) : null;

			if ( null !== $old ) {
				$patches[ $old['offset'] ] = array( strlen( $old['raw'] ), $old['translated'] ? self::revive( $old['raw'], $entry['raw'] ) : $entry['raw'] );
			} elseif ( null === $own ) {
				$append[] = $entry['raw'];
			} elseif ( ! $own['translated'] && $entry['translated'] && ! $entry['fuzzy'] ) {
				$patches[ $own['offset'] ] = array( strlen( $own['raw'] ), self::without_msgstr( $own['raw'] ) . self::msgstr_lines( $entry['raw'] ) );
			}
		}

		krsort( $patches );
		foreach ( $patches as $offset => $patch ) {
			$host = substr_replace( $host, $patch[1], $offset, $patch[0] );
		}

		if ( empty( $append ) ) {
			return $host;
		}

		$added    = implode( "\n\n", $append );
		$obsolete = ( new PoCatalog( $host ) )->obsolete_offset();

		if ( null !== $obsolete ) {
			return substr( $host, 0, $obsolete ) . $added . "\n\n" . substr( $host, $obsolete );
		}

		return rtrim( $host, "\n" ) . "\n\n" . $added . "\n";
	}

	/**
	 * Keys of the hub entries the host does not have.
	 *
	 * @param string $host Host PO/POT content.
	 * @param string $hub  Hub PO/POT content.
	 * @return array<int, string>
	 */
	public static function missing( string $host, string $hub ): array {
		$catalog = new PoCatalog( $host );
		$missing = array();

		foreach ( self::hub_entries( $hub ) as $entry ) {
			if ( null === $catalog->get( $entry['key'] ) ) {
				$missing[] = $entry['key'];
			}
		}

		return $missing;
	}

	/**
	 * Live hub entries without the header.
	 *
	 * @param string $hub Hub PO/POT content.
	 * @return array<int, array{key: string, raw: string, offset: int, obsolete: bool, fuzzy: bool, translated: bool, refs: array<int, string>}>
	 */
	private static function hub_entries( string $hub ): array {
		return array_values(
			array_filter(
				( new PoCatalog( $hub ) )->entries(),
				static fn( array $entry ): bool => '' !== $entry['key'] && ! $entry['obsolete']
			)
		);
	}

	/**
	 * An obsolete host entry made live again: the hub's comment lines (without
	 * a fuzzy flag, the translation is the host's), then the host's
	 * msgctxt/msgid/msgstr lines without the #~ marker.
	 *
	 * @param string $obsolete Obsolete host block.
	 * @param string $hub      Hub block.
	 * @return string
	 */
	private static function revive( string $obsolete, string $hub ): string {
		$lines = array();

		foreach ( explode( "\n", $hub ) as $line ) {
			if ( str_starts_with( $line, '#,' ) ) {
				$flags = array_diff( array_map( 'trim', explode( ',', substr( $line, 2 ) ) ), array( 'fuzzy', '' ) );
				$line  = empty( $flags ) ? '' : '#, ' . implode( ', ', $flags );
			}

			if ( str_starts_with( $line, '#' ) ) {
				$lines[] = $line;
			}
		}

		foreach ( explode( "\n", $obsolete ) as $line ) {
			if ( str_starts_with( $line, '#~' ) && ! str_starts_with( $line, '#~|' ) ) {
				$lines[] = ltrim( substr( $line, 2 ), ' ' );
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Block text before its first msgstr line (msgstr comes last in an entry).
	 *
	 * @param string $raw Entry block.
	 * @return string
	 */
	private static function without_msgstr( string $raw ): string {
		return (string) preg_replace( '/^msgstr[\s\S]*\z/m', '', $raw );
	}

	/**
	 * Block text from its first msgstr line on.
	 *
	 * @param string $raw Entry block.
	 * @return string
	 */
	private static function msgstr_lines( string $raw ): string {
		return preg_match( '/^msgstr[\s\S]*\z/m', $raw, $match ) ? $match[0] : '';
	}
}
