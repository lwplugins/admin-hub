<?php
/**
 * Detects the host plugin's own .pot refresh command.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tools\Sync;

/**
 * A package.json or composer.json script that runs `wp i18n make-pot` (or is
 * called "i18n") owns the plugin's .pot. make-pot scans assets/hub/ unless
 * the script excludes it, so the hub strings reach the .pot from there.
 */
final class I18nFlow {

	/**
	 * The command that refreshes the host .pot (npm run i18n), or null.
	 *
	 * @param string $root Host plugin root, with trailing slash.
	 * @return string|null
	 */
	public static function command( string $root ): ?string {
		$sources = array(
			'package.json'  => 'npm run ',
			'composer.json' => 'composer run ',
		);

		foreach ( $sources as $file => $runner ) {
			$json    = is_file( $root . $file ) ? json_decode( (string) file_get_contents( $root . $file ), true ) : null;
			$scripts = is_array( $json ) && is_array( $json['scripts'] ?? null ) ? $json['scripts'] : array();

			foreach ( $scripts as $name => $script ) {
				$body = is_array( $script ) ? implode( ' && ', array_filter( $script, 'is_string' ) ) : (string) $script;

				if ( 'i18n' === $name || str_contains( $body, 'make-pot' ) ) {
					return $runner . $name;
				}
			}
		}

		return null;
	}

	/**
	 * Whether the host's make-pot run skips the hub script (--exclude with
	 * assets or assets/hub), in which case its .pot will miss the hub strings.
	 *
	 * @param string $root Host plugin root, with trailing slash.
	 * @return bool
	 */
	public static function excludes_hub( string $root ): bool {
		$scripts = '';

		foreach ( array( 'package.json', 'composer.json' ) as $file ) {
			$scripts .= is_file( $root . $file ) ? (string) file_get_contents( $root . $file ) : '';
		}

		return (bool) preg_match( '#--exclude=[^\s"\']*(?<![\w-])assets(?:/hub)?/?(?:[,\s"\']|$)#', $scripts );
	}
}
