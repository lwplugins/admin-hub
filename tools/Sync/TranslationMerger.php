<?php
/**
 * Merges the hub translations into a host plugin's languages/.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tools\Sync;

use RuntimeException;

/**
 * For every hub .po: domain and source references rewritten to the host
 * layout, merged into languages/{domain}-{locale}.po (the plugin's own
 * translation wins on a shared msgid), compiled to .mo, and the JS
 * translations written as {domain}-{locale}-{md5}.json, where md5 is of the
 * plugin-relative script path (assets/hub/index.js), as WordPress looks it up.
 * The plugin's .pot gets the hub strings too. Needs msgcat, msgfmt and wp.
 */
final class TranslationMerger {

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
	 * Scratch directory for this run.
	 *
	 * @var string
	 */
	private string $tmp;

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
		$this->tmp    = sys_get_temp_dir() . '/lw-admin-hub-sync-' . getmypid() . '/';
	}

	/**
	 * Merge the .pot and every locale.
	 *
	 * @return void
	 * @throws RuntimeException When a tool is missing or a step fails.
	 */
	public function merge(): void {
		foreach ( array( 'msgcat', 'msgfmt', 'wp' ) as $tool ) {
			if ( '' === trim( (string) shell_exec( 'command -v ' . escapeshellarg( $tool ) ) ) ) {
				throw new RuntimeException( "{$tool} is required for the translations (gettext / WP-CLI)." );
			}
		}

		$domain = $this->target->text_domain;
		$this->reset_tmp();

		$pot = $this->rewrite( (string) file_get_contents( $this->hub . 'languages/' . CodeCopier::SOURCE_DOMAIN . '.pot' ) );
		$this->merge_into( "languages/{$domain}.pot", $pot, false );

		foreach ( glob( $this->hub . 'languages/' . CodeCopier::SOURCE_DOMAIN . '-*.po' ) ? glob( $this->hub . 'languages/' . CodeCopier::SOURCE_DOMAIN . '-*.po' ) : array() as $po ) {
			$locale = substr( basename( $po, '.po' ), strlen( CodeCopier::SOURCE_DOMAIN ) + 1 );
			$this->merge_locale( $locale, $this->rewrite( (string) file_get_contents( $po ) ) );
		}

		$this->run( 'rm -rf ' . escapeshellarg( $this->tmp ) );
	}

	/**
	 * Domain header and source references in host terms.
	 *
	 * @param string $po PO/POT content.
	 * @return string
	 */
	public function rewrite( string $po ): string {
		$po = str_replace( 'X-Domain: ' . CodeCopier::SOURCE_DOMAIN, 'X-Domain: ' . $this->target->text_domain, $po );

		return (string) preg_replace_callback(
			'/^#: (.+)$/m',
			function ( array $line ): string {
				$refs = str_replace(
					array( 'php/', 'build/index.js' ),
					array( $this->target->hub_php_dir(), CodeCopier::ASSETS_DIR . 'index.js' ),
					$line[1]
				);

				return '#: ' . $refs;
			},
			$po
		);
	}

	/**
	 * One locale: .po, .mo and the JS JSON.
	 *
	 * @param string $locale Locale (hu_HU).
	 * @param string $hub_po Rewritten hub PO.
	 * @return void
	 * @throws RuntimeException When make-json produced nothing.
	 */
	private function merge_locale( string $locale, string $hub_po ): void {
		$base = "languages/{$this->target->text_domain}-{$locale}";

		$this->merge_into( $base . '.po', $hub_po, true );

		$merged = $this->tmp . 'merged.po';
		$this->run( 'msgfmt -o ' . escapeshellarg( $this->tmp . 'out.mo' ) . ' ' . escapeshellarg( $merged ) );
		$this->writer->put( $base . '.mo', (string) file_get_contents( $this->tmp . 'out.mo' ) );

		// JSON from the hub strings only, named exactly like WordPress expects.
		$json_dir = $this->tmp . 'json/';
		mkdir( $json_dir, 0755, true );
		file_put_contents( $json_dir . basename( $base ) . '.po', $hub_po );
		$this->run( 'wp i18n make-json ' . escapeshellarg( $json_dir ) . ' --no-purge' );

		$json = $json_dir . basename( $base ) . '-' . md5( CodeCopier::ASSETS_DIR . 'index.js' ) . '.json';
		if ( ! is_file( $json ) ) {
			throw new RuntimeException( "wp i18n make-json produced no JSON for {$locale}." );
		}

		$this->writer->put( 'languages/' . basename( $json ), (string) file_get_contents( $json ) );
		$this->run( 'rm -rf ' . escapeshellarg( $json_dir ) );
	}

	/**
	 * Merge hub entries into a host .po/.pot (host entries win), keeping the
	 * result in merged.po for the .mo step.
	 *
	 * @param string $relative   Host file.
	 * @param string $hub        Rewritten hub content.
	 * @param bool   $create_new Create the host file when it does not exist.
	 * @return void
	 */
	private function merge_into( string $relative, string $hub, bool $create_new ): void {
		$current = $this->writer->read( $relative );
		$merged  = $this->tmp . 'merged.po';

		if ( null === $current ) {
			if ( ! $create_new ) {
				$this->writer->note( "{$relative} does not exist; skipped" );
				return;
			}
			file_put_contents( $merged, $hub );
		} else {
			file_put_contents( $this->tmp . 'host.po', $current );
			file_put_contents( $this->tmp . 'hub.po', $hub );
			// .pot files come from make-pot (unwrapped), .po files from msgmerge (wrapped).
			$wrap = str_ends_with( $relative, '.pot' ) ? '--no-wrap ' : '';
			$this->run( 'msgcat --use-first ' . $wrap . '-o ' . escapeshellarg( $merged ) . ' ' . escapeshellarg( $this->tmp . 'host.po' ) . ' ' . escapeshellarg( $this->tmp . 'hub.po' ) );
		}

		$this->writer->put( $relative, (string) file_get_contents( $merged ) );
	}

	/**
	 * Empty scratch directory.
	 *
	 * @return void
	 */
	private function reset_tmp(): void {
		$this->run( 'rm -rf ' . escapeshellarg( $this->tmp ) );
		mkdir( $this->tmp, 0755, true );
	}

	/**
	 * Run a shell command or fail.
	 *
	 * @param string $command Command.
	 * @return void
	 * @throws RuntimeException When it fails.
	 */
	private function run( string $command ): void {
		exec( $command . ' 2>&1', $output, $code );

		if ( 0 !== $code ) {
			throw new RuntimeException( "Command failed ({$code}): {$command}\n" . implode( "\n", $output ) );
		}
	}
}
