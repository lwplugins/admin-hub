<?php
/**
 * Minimal read-only view of a PO/POT file.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tools\Sync;

/**
 * Splits a PO/POT file into its entry blocks (runs of non-empty lines) and
 * remembers where each block sits in the source, so a merge can add or patch
 * single entries and leave every other byte of the file alone.
 */
final class PoCatalog {

	/**
	 * Entries in file order. The header (msgid "") has the key ''.
	 *
	 * @var array<int, array{key: string, raw: string, offset: int, obsolete: bool, fuzzy: bool, translated: bool, refs: array<int, string>}>
	 */
	private array $entries = array();

	/**
	 * Entry index by key.
	 *
	 * @var array<string, int>
	 */
	private array $index = array();

	/**
	 * Constructor.
	 *
	 * @param string $po PO/POT content.
	 */
	public function __construct( string $po ) {
		preg_match_all( '/(?:^[^\n]*\S[^\n]*(?:\n|$))+/m', $po, $blocks, PREG_OFFSET_CAPTURE );

		foreach ( $blocks[0] as $block ) {
			$entry = $this->parse( rtrim( $block[0], "\n" ), (int) $block[1] );

			if ( null === $entry ) {
				continue;
			}

			$this->entries[] = $entry;

			if ( ! $entry['obsolete'] && ! isset( $this->index[ $entry['key'] ] ) ) {
				$this->index[ $entry['key'] ] = count( $this->entries ) - 1;
			}
		}
	}

	/**
	 * Every entry, in file order.
	 *
	 * @return array<int, array{key: string, raw: string, offset: int, obsolete: bool, fuzzy: bool, translated: bool, refs: array<int, string>}>
	 */
	public function entries(): array {
		return $this->entries;
	}

	/**
	 * Live (not obsolete) entry by key.
	 *
	 * @param string $key Entry key (msgctxt EOT msgid).
	 * @return array{key: string, raw: string, offset: int, obsolete: bool, fuzzy: bool, translated: bool, refs: array<int, string>}|null
	 */
	public function get( string $key ): ?array {
		return isset( $this->index[ $key ] ) ? $this->entries[ $this->index[ $key ] ] : null;
	}

	/**
	 * Obsolete (#~) entry by key.
	 *
	 * @param string $key Entry key (msgctxt EOT msgid).
	 * @return array{key: string, raw: string, offset: int, obsolete: bool, fuzzy: bool, translated: bool, refs: array<int, string>}|null
	 */
	public function get_obsolete( string $key ): ?array {
		foreach ( $this->entries as $entry ) {
			if ( $entry['obsolete'] && $key === $entry['key'] ) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Offset of the first obsolete (#~) entry, or null.
	 *
	 * @return int|null
	 */
	public function obsolete_offset(): ?int {
		foreach ( $this->entries as $entry ) {
			if ( $entry['obsolete'] ) {
				return $entry['offset'];
			}
		}

		return null;
	}

	/**
	 * One block; null when it holds no msgid (a comment-only block).
	 *
	 * @param string $raw    Block text without the trailing newline.
	 * @param int    $offset Byte offset in the file.
	 * @return array{key: string, raw: string, offset: int, obsolete: bool, fuzzy: bool, translated: bool, refs: array<int, string>}|null
	 */
	private function parse( string $raw, int $offset ): ?array {
		$fields   = array();
		$current  = '';
		$refs     = array();
		$fuzzy    = false;
		$obsolete = false;

		foreach ( explode( "\n", $raw ) as $line ) {
			if ( str_starts_with( $line, '#~|' ) ) {
				continue;
			} elseif ( str_starts_with( $line, '#~' ) ) {
				$obsolete = true;
				$line     = ltrim( substr( $line, 2 ) );
			} elseif ( str_starts_with( $line, '#:' ) ) {
				$refs = array_merge( $refs, preg_split( '/\s+/', trim( substr( $line, 2 ) ), -1, PREG_SPLIT_NO_EMPTY ) );
				continue;
			} elseif ( str_starts_with( $line, '#,' ) ) {
				$fuzzy = $fuzzy || str_contains( $line, 'fuzzy' );
				continue;
			}

			if ( preg_match( '/^(msgctxt|msgid_plural|msgid|msgstr(?:\[\d+\])?)\s+"(.*)"\s*$/', $line, $match ) ) {
				$current            = $match[1];
				$fields[ $current ] = $match[2];
			} elseif ( '' !== $current && preg_match( '/^"(.*)"\s*$/', $line, $match ) ) {
				$fields[ $current ] .= $match[1];
			}
		}

		if ( ! isset( $fields['msgid'] ) ) {
			return null;
		}

		$strings = array_filter( $fields, static fn( string $name ): bool => str_starts_with( $name, 'msgstr' ), ARRAY_FILTER_USE_KEY );

		return array(
			'key'        => isset( $fields['msgctxt'] ) ? $fields['msgctxt'] . "\x04" . $fields['msgid'] : $fields['msgid'],
			'raw'        => $raw,
			'offset'     => $offset,
			'obsolete'   => $obsolete,
			'fuzzy'      => $fuzzy,
			'translated' => '' !== implode( '', $strings ),
			'refs'       => $refs,
		);
	}
}
