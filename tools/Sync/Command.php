<?php
/**
 * The bin/sync.php command.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tools\Sync;

use LightweightPlugins\AdminHub\Hub;
use RuntimeException;

/**
 * Parses the arguments, runs the copy steps and prints the change log.
 */
final class Command {

	/**
	 * Usage text.
	 */
	private const USAGE = <<<'TXT'
Usage: php bin/sync.php <plugin-dir> [options]

Copies the LW Plugins hub into a host plugin:
  PHP     -> {psr4-dir}/Admin/Hub/  (namespace {PluginNS}\Admin\Hub, text domain rewritten)
  assets  -> assets/hub/            (index.js/.css, index.asset.json, icons/)
  i18n    -> languages/             (.po merged plugin-first, .mo, JS JSON by md5 of assets/hub/index.js
                                     from the merged .po; the .pot is left to the plugin's i18n script,
                                     without one only the missing hub entries are added)

Options:
  --namespace=NS      Plugin root namespace (default: composer.json PSR-4)
  --php-dir=DIR       PSR-4 directory (default: composer.json PSR-4)
  --text-domain=SLUG  Text domain (default: main plugin file header)
  --shim              Also replace Admin/ParentPage.php with the delegating shim
  --no-i18n           Skip the translations
  --dry-run           Only print what would change

TXT;

	/**
	 * Run with the CLI arguments; returns the exit code.
	 *
	 * @param array<int, string> $argv CLI arguments.
	 * @return int
	 */
	public static function run( array $argv ): int {
		$args    = array_slice( $argv, 1 );
		$options = array();
		$flags   = array();
		$paths   = array();

		foreach ( $args as $arg ) {
			if ( preg_match( '/^--([a-z-]+)=(.*)$/', $arg, $match ) ) {
				$options[ $match[1] ] = $match[2];
			} elseif ( str_starts_with( $arg, '--' ) ) {
				$flags[] = substr( $arg, 2 );
			} else {
				$paths[] = $arg;
			}
		}

		if ( in_array( 'help', $flags, true ) ) {
			echo self::USAGE;
			return 0;
		}

		if ( 1 !== count( $paths ) ) {
			fwrite( STDERR, self::USAGE );
			return 2;
		}

		try {
			$hub    = dirname( __DIR__, 2 ) . '/';
			$target = new Target( $paths[0], $options );
			$writer = new Writer( $target->root, in_array( 'dry-run', $flags, true ) );

			( new CodeCopier( $hub, $target, $writer ) )->copy_php( Hub::VERSION );
			( new CodeCopier( $hub, $target, $writer ) )->copy_assets();

			if ( ! in_array( 'no-i18n', $flags, true ) ) {
				( new TranslationMerger( $hub, $target, $writer ) )->merge();
			}

			if ( in_array( 'shim', $flags, true ) ) {
				( new ShimWriter( $hub, $target, $writer ) )->write();
			}

			self::report( $target, $writer, in_array( 'dry-run', $flags, true ) );
		} catch ( RuntimeException $error ) {
			fwrite( STDERR, 'sync: ' . $error->getMessage() . "\n" );
			return 1;
		}

		return 0;
	}

	/**
	 * Print the change log and the manual steps still open.
	 *
	 * @param Target $target  Host plugin.
	 * @param Writer $writer  Writer with the log.
	 * @param bool   $dry_run Dry run.
	 * @return void
	 */
	private static function report( Target $target, Writer $writer, bool $dry_run ): void {
		$log = $writer->log();

		echo 'admin-hub ' . Hub::VERSION . ' -> ' . $target->root . "\n";
		echo '  namespace ' . $target->hub_namespace() . ', text domain ' . $target->text_domain . ( $dry_run ? ' (dry run)' : '' ) . "\n";
		echo empty( $log ) ? "  no changes\n" : '  ' . implode( "\n  ", $log ) . "\n";

		$bootstrap = (string) shell_exec( 'grep -rl ' . escapeshellarg( 'Hub\\Hub::init\|Hub::init(' ) . ' ' . escapeshellarg( $target->root . $target->php_dir ) . ' ' . escapeshellarg( $target->root ) . '*.php 2>/dev/null' );

		if ( '' === trim( $bootstrap ) ) {
			echo "  TODO     call Admin\\Hub\\Hub::init( <main plugin file> ) from the plugin bootstrap (every request)\n";
		}
	}
}
