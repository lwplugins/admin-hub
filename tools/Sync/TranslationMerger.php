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
 * layout, then merged plugin-first and format-preserving (PoMerger) into
 * languages/{domain}-{locale}.po, compiled to .mo, and the hub script's JS
 * translations written as {domain}-{locale}-{md5}.json from that merged .po,
 * so the plugin's own wording of a hub string wins in the browser too. md5 is
 * of the plugin-relative script path (assets/hub/index.js), as WordPress
 * looks it up.
 *
 * The .pot is left to the plugin's own i18n flow when it has one (I18nFlow);
 * otherwise only the missing hub entries are added. Needs msgfmt and wp.
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
		$this->tmp    = sys_get_temp_dir() . '/lw-admin-hub-sync-' . getmypid() . '-' . bin2hex( random_bytes( 4 ) ) . '/';
	}

	/**
	 * Merge the .pot and every locale.
	 *
	 * @return void
	 * @throws RuntimeException When a tool is missing or a step fails.
	 */
	public function merge(): void {
		foreach ( array( 'msgfmt', 'wp' ) as $tool ) {
			if ( '' === trim( (string) shell_exec( 'command -v ' . escapeshellarg( $tool ) ) ) ) {
				throw new RuntimeException( "{$tool} is required for the translations (gettext / WP-CLI)." );
			}
		}

		$this->reset_tmp();

		try {
			$this->merge_pot( $this->rewrite( (string) file_get_contents( $this->hub . 'languages/' . CodeCopier::SOURCE_DOMAIN . '.pot' ) ) );

			$pos = glob( $this->hub . 'languages/' . CodeCopier::SOURCE_DOMAIN . '-*.po' );
			foreach ( $pos ? $pos : array() as $po ) {
				$locale = substr( basename( $po, '.po' ), strlen( CodeCopier::SOURCE_DOMAIN ) + 1 );
				$this->merge_locale( $locale, $this->rewrite( (string) file_get_contents( $po ) ) );
			}
		} finally {
			$this->run( 'rm -rf ' . escapeshellarg( $this->tmp ) );
		}
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
	 * The .pot: the plugin's i18n flow owns it; without one, only the missing
	 * hub entries are added.
	 *
	 * @param string $hub_pot Rewritten hub POT.
	 * @return void
	 */
	private function merge_pot( string $hub_pot ): void {
		$relative = "languages/{$this->target->text_domain}.pot";
		$current  = $this->writer->read( $relative );
		$command  = I18nFlow::command( $this->target->root );

		if ( null !== $command && I18nFlow::excludes_hub( $this->target->root ) ) {
			$this->writer->note( "the plugin's make-pot excludes assets/hub/; its .pot will lose the hub strings (drop that exclude)" );
			$command = null;
		}

		if ( null === $current ) {
			$this->writer->note( "{$relative} does not exist; skipped" . ( null !== $command ? " (run `{$command}`)" : '' ) );
			return;
		}

		$missing = count( PoMerger::missing( $current, $hub_pot ) );

		if ( 0 === $missing ) {
			return;
		}

		if ( null !== $command ) {
			$this->writer->note( "{$relative} lacks {$missing} hub string(s); run `{$command}` in the plugin to refresh it" );
			return;
		}

		$this->writer->put( $relative, PoMerger::merge( $current, $hub_pot ) );
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
		$base    = "languages/{$this->target->text_domain}-{$locale}";
		$current = $this->writer->read( $base . '.po' );
		$merged  = null === $current ? $hub_po : PoMerger::merge( $current, $hub_po );

		$this->writer->put( $base . '.po', $merged );

		file_put_contents( $this->tmp . 'merged.po', $merged );
		$this->run( 'msgfmt -o ' . escapeshellarg( $this->tmp . 'out.mo' ) . ' ' . escapeshellarg( $this->tmp . 'merged.po' ) );
		$this->writer->put( $base . '.mo', (string) file_get_contents( $this->tmp . 'out.mo' ) );

		// JSON from the merged .po (plugin wording first), hub script strings only.
		$script   = CodeCopier::ASSETS_DIR . 'index.js';
		$json_dir = $this->tmp . 'json/';
		mkdir( $json_dir, 0755, true );
		file_put_contents( $json_dir . basename( $base ) . '.po', HubScriptPo::build( $merged, $hub_po, $script ) );
		$this->run( 'wp i18n make-json ' . escapeshellarg( $json_dir ) . ' --no-purge' );

		$json = $json_dir . basename( $base ) . '-' . md5( $script ) . '.json';
		if ( ! is_file( $json ) ) {
			throw new RuntimeException( "wp i18n make-json produced no JSON for {$locale}." );
		}

		$this->writer->put( 'languages/' . basename( $json ), (string) file_get_contents( $json ) );
		$this->run( 'rm -rf ' . escapeshellarg( $json_dir ) );
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
