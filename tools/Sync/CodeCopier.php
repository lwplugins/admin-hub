<?php
/**
 * Copies the hub PHP classes and built assets into a host plugin.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tools\Sync;

use RuntimeException;

/**
 * PHP: namespace and text domain rewritten, a "generated" note added.
 * Assets: the build (text domain rewritten in index.js), the asset manifest
 * as JSON (no generated PHP for the host's phpcs to scan) and the icons.
 */
final class CodeCopier {

	/**
	 * Source namespace of the hub classes.
	 */
	public const SOURCE_NAMESPACE = 'LightweightPlugins\\AdminHub';

	/**
	 * Source text domain.
	 */
	public const SOURCE_DOMAIN = 'lw-admin-hub';

	/**
	 * Hub directory inside the host plugin (mirrors Assets::DIR).
	 */
	public const ASSETS_DIR = 'assets/hub/';

	/**
	 * Admin-hub repo root, with trailing slash.
	 *
	 * @var string
	 */
	private string $hub;

	/**
	 * Host plugin.
	 *
	 * @var Target
	 */
	private Target $target;

	/**
	 * File writer.
	 *
	 * @var Writer
	 */
	private Writer $writer;

	/**
	 * Constructor.
	 *
	 * @param string $hub    Admin-hub repo root, with trailing slash.
	 * @param Target $target Host plugin.
	 * @param Writer $writer File writer.
	 */
	public function __construct( string $hub, Target $target, Writer $writer ) {
		$this->hub    = $hub;
		$this->target = $target;
		$this->writer = $writer;
	}

	/**
	 * Copy the PHP classes; remove classes the hub no longer has.
	 *
	 * @param string $version Hub version, for the generated note.
	 * @return void
	 */
	public function copy_php( string $version ): void {
		$dir  = $this->target->hub_php_dir();
		$keep = array();

		foreach ( glob( $this->hub . 'php/*.php' ) ? glob( $this->hub . 'php/*.php' ) : array() as $file ) {
			$keep[] = basename( $file );
			$this->writer->put( $dir . basename( $file ), $this->rewrite_php( (string) file_get_contents( $file ), $version ) );
		}

		$this->writer->prune( $dir, '*.php', $keep );
	}

	/**
	 * Namespace, package and text domain of one class, plus the note.
	 *
	 * @param string $code    Source code.
	 * @param string $version Hub version.
	 * @return string
	 */
	public function rewrite_php( string $code, string $version ): string {
		$code = str_replace( '@package ' . self::SOURCE_NAMESPACE, '@package ' . $this->target->plugin_namespace, $code );
		$code = str_replace( self::SOURCE_NAMESPACE, $this->target->hub_namespace(), $code );
		$code = str_replace( "'" . self::SOURCE_DOMAIN . "'", "'" . $this->target->text_domain . "'", $code );

		// First docblock line stays the summary; the note goes right after it.
		return (string) preg_replace(
			'/^(<\?php\n\/\*\*\n \* [^\n]+\n)/',
			"$1 *\n * Synced from lwplugins/admin-hub {$version} by bin/sync.php. Do not edit\n * this copy: change the admin-hub repo and sync again.\n",
			$code,
			1
		);
	}

	/**
	 * Copy the build, the manifest (as JSON) and the icons.
	 *
	 * @return void
	 * @throws RuntimeException When the hub build is missing.
	 */
	public function copy_assets(): void {
		$build = $this->hub . 'build/';

		if ( ! is_file( $build . 'index.js' ) || ! is_file( $build . 'index.asset.php' ) ) {
			throw new RuntimeException( 'The hub build is missing: run "npm run build" in admin-hub first.' );
		}

		$js = str_replace( '"' . self::SOURCE_DOMAIN . '"', '"' . $this->target->text_domain . '"', (string) file_get_contents( $build . 'index.js' ) );
		$this->writer->put( self::ASSETS_DIR . 'index.js', $js );

		foreach ( array( 'index.css', 'index-rtl.css' ) as $file ) {
			$this->writer->put( self::ASSETS_DIR . $file, (string) file_get_contents( $build . $file ) );
		}

		$asset = include $build . 'index.asset.php';
		$this->writer->put( self::ASSETS_DIR . 'index.asset.json', json_encode( $asset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );

		$keep = array();
		foreach ( glob( $this->hub . 'icons/*.svg' ) ? glob( $this->hub . 'icons/*.svg' ) : array() as $icon ) {
			$keep[] = basename( $icon );
			$this->writer->put( self::ASSETS_DIR . 'icons/' . basename( $icon ), (string) file_get_contents( $icon ) );
		}

		$this->writer->prune( self::ASSETS_DIR . 'icons/', '*.svg', $keep );
		$this->writer->prune( self::ASSETS_DIR, '*.php', array() );
	}
}
