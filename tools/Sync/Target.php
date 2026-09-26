<?php
/**
 * Where and how the hub goes into one host plugin.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tools\Sync;

use RuntimeException;

/**
 * Reads the host plugin's namespace and PSR-4 directory from composer.json
 * and its text domain from the main plugin file header. Every value can be
 * overridden from the command line.
 */
final class Target {

	/**
	 * Host plugin root, with trailing slash.
	 *
	 * @var string
	 */
	public string $root;

	/**
	 * Plugin root namespace, without trailing backslash (LightweightPlugins\Disable).
	 *
	 * @var string
	 */
	public string $plugin_namespace;

	/**
	 * PSR-4 directory of that namespace, relative, with trailing slash (includes/).
	 *
	 * @var string
	 */
	public string $php_dir;

	/**
	 * Text domain (lw-disable).
	 *
	 * @var string
	 */
	public string $text_domain;

	/**
	 * Constructor.
	 *
	 * @param string                $root    Host plugin directory.
	 * @param array<string, string> $options Overrides: namespace, php-dir, text-domain.
	 * @throws RuntimeException When a value cannot be detected.
	 */
	public function __construct( string $root, array $options ) {
		$real = realpath( $root );

		if ( false === $real || ! is_dir( $real ) ) {
			throw new RuntimeException( "Target directory not found: {$root}" );
		}

		$this->root = rtrim( $real, '/' ) . '/';

		list( $namespace, $dir ) = $this->detect_psr4();

		$this->plugin_namespace = trim( $options['namespace'] ?? $namespace, '\\' );
		$this->php_dir          = rtrim( $options['php-dir'] ?? $dir, '/' ) . '/';
		$this->text_domain      = $options['text-domain'] ?? $this->detect_text_domain();

		if ( ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $this->plugin_namespace ) ) {
			throw new RuntimeException( "Invalid namespace: {$this->plugin_namespace}" );
		}

		if ( ! preg_match( '/^[a-z0-9-]+$/', $this->text_domain ) ) {
			throw new RuntimeException( "Invalid text domain: {$this->text_domain}" );
		}
	}

	/**
	 * Namespace of the synced hub classes.
	 *
	 * @return string
	 */
	public function hub_namespace(): string {
		return $this->plugin_namespace . '\\Admin\\Hub';
	}

	/**
	 * Relative directory of the synced hub classes, with trailing slash.
	 *
	 * @return string
	 */
	public function hub_php_dir(): string {
		return $this->php_dir . 'Admin/Hub/';
	}

	/**
	 * First non-test PSR-4 mapping of composer.json.
	 *
	 * @return array{0: string, 1: string}
	 * @throws RuntimeException When composer.json has none.
	 */
	private function detect_psr4(): array {
		$file     = $this->root . 'composer.json';
		$composer = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
		$map      = is_array( $composer ) ? ( $composer['autoload']['psr-4'] ?? array() ) : array();

		foreach ( (array) $map as $namespace => $dir ) {
			if ( is_string( $namespace ) && is_string( $dir ) && ! str_contains( $namespace, '\\Tests\\' ) ) {
				return array( $namespace, $dir );
			}
		}

		throw new RuntimeException( 'No PSR-4 autoload mapping in composer.json; pass --namespace and --php-dir.' );
	}

	/**
	 * Text Domain header of the main plugin file ({dirname}.php).
	 *
	 * @return string
	 * @throws RuntimeException When the header is missing.
	 */
	private function detect_text_domain(): string {
		$main = $this->root . basename( rtrim( $this->root, '/' ) ) . '.php';
		$head = is_file( $main ) ? (string) file_get_contents( $main, false, null, 0, 8192 ) : '';

		if ( preg_match( '/^[\s*#@]*Text Domain:\s*([a-z0-9-]+)/mi', $head, $match ) ) {
			return $match[1];
		}

		throw new RuntimeException( "No Text Domain header in {$main}; pass --text-domain." );
	}
}
