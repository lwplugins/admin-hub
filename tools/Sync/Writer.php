<?php
/**
 * Change-aware file writes for the sync tool.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tools\Sync;

use RuntimeException;

/**
 * Writes only when the content differs and logs every change, so a second
 * run on an up-to-date plugin reports "no changes" (idempotent).
 */
final class Writer {

	/**
	 * Host plugin root, with trailing slash.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Report only, touch nothing.
	 *
	 * @var bool
	 */
	private bool $dry_run;

	/**
	 * Change log lines.
	 *
	 * @var array<int, string>
	 */
	private array $log = array();

	/**
	 * Constructor.
	 *
	 * @param string $root    Host plugin root, with trailing slash.
	 * @param bool   $dry_run Report only.
	 */
	public function __construct( string $root, bool $dry_run ) {
		$this->root    = $root;
		$this->dry_run = $dry_run;
	}

	/**
	 * Write a file relative to the root when its content changed.
	 *
	 * @param string $relative Relative path.
	 * @param string $content  New content.
	 * @return void
	 * @throws RuntimeException When the write fails.
	 */
	public function put( string $relative, string $content ): void {
		$path   = $this->root . $relative;
		$exists = is_file( $path );

		if ( $exists && file_get_contents( $path ) === $content ) {
			return;
		}

		$this->log[] = ( $exists ? 'updated  ' : 'created  ' ) . $relative;

		if ( $this->dry_run ) {
			return;
		}

		if ( ! is_dir( dirname( $path ) ) && ! mkdir( dirname( $path ), 0755, true ) ) {
			throw new RuntimeException( "Cannot create directory for {$relative}" );
		}

		if ( false === file_put_contents( $path, $content ) ) {
			throw new RuntimeException( "Cannot write {$relative}" );
		}
	}

	/**
	 * Delete files in a directory that match a glob but are not kept.
	 *
	 * @param string             $relative_dir Relative directory, trailing slash.
	 * @param string             $pattern      Glob inside it (*.php).
	 * @param array<int, string> $keep         Basenames to keep.
	 * @return void
	 */
	public function prune( string $relative_dir, string $pattern, array $keep ): void {
		foreach ( glob( $this->root . $relative_dir . $pattern ) ? glob( $this->root . $relative_dir . $pattern ) : array() as $path ) {
			if ( in_array( basename( $path ), $keep, true ) ) {
				continue;
			}

			$this->log[] = 'removed  ' . $relative_dir . basename( $path );

			if ( ! $this->dry_run ) {
				unlink( $path );
			}
		}
	}

	/**
	 * Current content of a file relative to the root, or null.
	 *
	 * @param string $relative Relative path.
	 * @return string|null
	 */
	public function read( string $relative ): ?string {
		$path = $this->root . $relative;

		return is_file( $path ) ? (string) file_get_contents( $path ) : null;
	}

	/**
	 * Add a note to the report.
	 *
	 * @param string $line Note.
	 * @return void
	 */
	public function note( string $line ): void {
		$this->log[] = 'note     ' . $line;
	}

	/**
	 * Change log lines.
	 *
	 * @return array<int, string>
	 */
	public function log(): array {
		return $this->log;
	}
}
