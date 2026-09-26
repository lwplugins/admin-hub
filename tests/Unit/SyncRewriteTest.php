<?php
/**
 * bin/sync.php rewrite tests.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tests\Unit;

use LightweightPlugins\AdminHub\Tools\Sync\CodeCopier;
use LightweightPlugins\AdminHub\Tools\Sync\Target;
use LightweightPlugins\AdminHub\Tools\Sync\TranslationMerger;
use LightweightPlugins\AdminHub\Tools\Sync\Writer;
use PHPUnit\Framework\TestCase;

/**
 * Target detection and the PHP / PO rewrites.
 */
final class SyncRewriteTest extends TestCase {

	/**
	 * Fake host plugin directory.
	 *
	 * @var string
	 */
	private string $dir;

	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/lw-hub-test-' . uniqid() . '/lw-disable';
		mkdir( $this->dir, 0755, true );
		file_put_contents(
			$this->dir . '/composer.json',
			(string) json_encode( array( 'autoload' => array( 'psr-4' => array( 'LightweightPlugins\\Disable\\' => 'includes/' ) ) ) )
		);
		file_put_contents( $this->dir . '/lw-disable.php', "<?php\n/**\n * Plugin Name: LW Disable\n * Text Domain:       lw-disable\n */\n" );
	}

	protected function tearDown(): void {
		exec( 'rm -rf ' . escapeshellarg( dirname( $this->dir ) ) );
		parent::tearDown();
	}

	public function test_target_is_read_from_composer_and_header(): void {
		$target = new Target( $this->dir, array() );

		$this->assertSame( 'LightweightPlugins\\Disable', $target->plugin_namespace );
		$this->assertSame( 'LightweightPlugins\\Disable\\Admin\\Hub', $target->hub_namespace() );
		$this->assertSame( 'includes/Admin/Hub/', $target->hub_php_dir() );
		$this->assertSame( 'lw-disable', $target->text_domain );
	}

	public function test_php_rewrite(): void {
		$target = new Target( $this->dir, array() );
		$copier = new CodeCopier( dirname( __DIR__, 2 ) . '/', $target, new Writer( $target->root, true ) );
		$source = "<?php\n/**\n * Summary.\n *\n * @package LightweightPlugins\\AdminHub\n */\n\nnamespace LightweightPlugins\\AdminHub;\n\n__( 'Hi', 'lw-admin-hub' );\n";

		$out = $copier->rewrite_php( $source, '1.2.3' );

		$this->assertStringContainsString( "namespace LightweightPlugins\\Disable\\Admin\\Hub;", $out );
		$this->assertStringContainsString( '@package LightweightPlugins\\Disable', $out );
		$this->assertStringContainsString( "__( 'Hi', 'lw-disable' )", $out );
		$this->assertStringContainsString( " * Summary.\n *\n * Synced from lwplugins/admin-hub 1.2.3", $out );
		$this->assertStringNotContainsString( 'AdminHub', $out );
	}

	public function test_po_rewrite_points_references_at_the_host_layout(): void {
		$target = new Target( $this->dir, array() );
		$merger = new TranslationMerger( dirname( __DIR__, 2 ) . '/', $target, new Writer( $target->root, true ) );

		$out = $merger->rewrite( "\"X-Domain: lw-admin-hub\\n\"\n\n#: php/Page.php:57\n#: build/index.js:5\nmsgid \"LW Plugins\"\n" );

		$this->assertStringContainsString( 'X-Domain: lw-disable', $out );
		$this->assertStringContainsString( '#: includes/Admin/Hub/Page.php:57', $out );
		$this->assertStringContainsString( '#: assets/hub/index.js:5', $out );
	}
}
