<?php
/**
 * Writes the ParentPage compatibility shim into a host plugin.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tools\Sync;

/**
 * Replaces the plugin's own Admin/ParentPage.php with a thin class that
 * delegates to the synced hub (templates/ParentPage.php.tpl). Opt-in
 * (--shim): the plugin may have local changes in ParentPage worth a look.
 */
final class ShimWriter {

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
	 * Write the shim. NoticeManager::init() is kept when the plugin has it
	 * (lw-scan only has register(), called from its Plugin class).
	 *
	 * @return void
	 */
	public function write(): void {
		$notices = (string) $this->writer->read( $this->target->php_dir . 'Admin/NoticeManager.php' );
		$init    = str_contains( $notices, 'public static function init(' ) ? "\t\tNoticeManager::init();\n" : '';

		if ( '' === $init ) {
			$this->writer->note( 'NoticeManager::init() not found; the shim does not call it (check that notice isolation is registered elsewhere)' );
		}

		$code = str_replace(
			array( '{{PLUGIN_NAMESPACE}}', '{{NOTICE_INIT}}' ),
			array( $this->target->plugin_namespace, $init ),
			(string) file_get_contents( $this->hub . 'templates/ParentPage.php.tpl' )
		);

		$this->writer->put( $this->target->php_dir . 'Admin/ParentPage.php', $code );
	}
}
