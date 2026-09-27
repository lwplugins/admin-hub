<?php
/**
 * The PO that the hub script's JSON translations are made from.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tools\Sync;

/**
 * Picks, from the host's merged PO, the entries the hub script uses (the hub
 * PO entries referenced from the script) and points them at that script only,
 * so `wp i18n make-json` writes exactly one JSON for it — with the host's
 * header and the host's wording, as the plugin's own make-json would.
 */
final class HubScriptPo {

	/**
	 * Build the restricted PO.
	 *
	 * @param string $host_po Host PO after the merge.
	 * @param string $hub_po  Hub PO (rewritten to host terms).
	 * @param string $script  Plugin-relative script path (assets/hub/index.js).
	 * @return string
	 */
	public static function build( string $host_po, string $hub_po, string $script ): string {
		$keys = array();

		foreach ( ( new PoCatalog( $hub_po ) )->entries() as $entry ) {
			if ( ! $entry['obsolete'] && self::references( $entry['refs'], $script ) ) {
				$keys[ $entry['key'] ] = true;
			}
		}

		$blocks = array();

		foreach ( ( new PoCatalog( $host_po ) )->entries() as $entry ) {
			if ( $entry['obsolete'] ) {
				continue;
			}

			if ( '' === $entry['key'] ) {
				$blocks[] = $entry['raw'];
			} elseif ( isset( $keys[ $entry['key'] ] ) ) {
				$body     = (string) preg_replace( '/^#:.*\n?/m', '', $entry['raw'] );
				$blocks[] = '#: ' . $script . "\n" . $body;
			}
		}

		return implode( "\n\n", $blocks ) . "\n";
	}

	/**
	 * Whether a reference list points at the script.
	 *
	 * @param array<int, string> $refs   References (path:line).
	 * @param string             $script Script path.
	 * @return bool
	 */
	private static function references( array $refs, string $script ): bool {
		foreach ( $refs as $ref ) {
			if ( $ref === $script || str_starts_with( $ref, $script . ':' ) ) {
				return true;
			}
		}

		return false;
	}
}
