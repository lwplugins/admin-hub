<?php
/**
 * bin/sync.php translation merge tests, end to end on a fake host plugin.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tests\Unit;

use LightweightPlugins\AdminHub\Tools\Sync\I18nFlow;
use LightweightPlugins\AdminHub\Tools\Sync\Target;
use LightweightPlugins\AdminHub\Tools\Sync\TranslationMerger;
use LightweightPlugins\AdminHub\Tools\Sync\Writer;
use PHPUnit\Framework\TestCase;

/**
 * The hub .po/.pot/JSON against a host that translates a hub string its own way.
 */
final class SyncTranslationMergerTest extends TestCase {

	private const POT = <<<'PO'
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"X-Domain: lw-demo\n"

#. translators: %s: time of day.
#: includes/Format.php:35
#: build/index.js:1
#, php-format,js-format
msgid "today %s"
msgstr ""

PO;

	private const PO = <<<'PO'
msgid ""
msgstr ""
"PO-Revision-Date: 2026-09-24 19:57+0200\n"
"Language: hu\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\n"
"X-Domain: lw-demo\n"

#: assets/hub/index.js:1 build/index.js:1
msgid "Try again"
msgstr "Próbáld újra"

#: assets/hub/index.js:4
msgid "All"
msgstr "Összes"

PO;

	/**
	 * Fake host plugin directory.
	 *
	 * @var string
	 */
	private string $dir;

	protected function setUp(): void {
		parent::setUp();

		if ( '' === trim( (string) shell_exec( 'command -v msgfmt' ) ) || '' === trim( (string) shell_exec( 'command -v wp' ) ) ) {
			$this->markTestSkipped( 'msgfmt and wp are needed.' );
		}

		$this->dir = sys_get_temp_dir() . '/lw-hub-i18n-' . uniqid() . '/lw-demo';
		mkdir( $this->dir . '/languages', 0755, true );
		file_put_contents( $this->dir . '/composer.json', (string) json_encode( array( 'autoload' => array( 'psr-4' => array( 'LightweightPlugins\\Demo\\' => 'includes/' ) ) ) ) );
		file_put_contents( $this->dir . '/package.json', (string) json_encode( array( 'scripts' => array( 'i18n' => 'wp i18n make-pot . languages/lw-demo.pot --exclude=node_modules,vendor,src' ) ) ) );
		file_put_contents( $this->dir . '/lw-demo.php', "<?php\n/**\n * Plugin Name: LW Demo\n * Text Domain: lw-demo\n */\n" );
		file_put_contents( $this->dir . '/languages/lw-demo.pot', self::POT );
		file_put_contents( $this->dir . '/languages/lw-demo-hu_HU.po', self::PO );
	}

	protected function tearDown(): void {
		if ( isset( $this->dir ) ) {
			exec( 'rm -rf ' . escapeshellarg( dirname( $this->dir ) ) );
		}
		parent::tearDown();
	}

	public function test_json_keeps_the_plugin_wording_and_adds_hub_only_strings(): void {
		$this->sync();

		$messages = $this->json()['locale_data']['messages'];

		$this->assertSame( array( 'Próbáld újra' ), $messages['Try again'] );
		$this->assertSame( array( 'Összes' ), $messages['All'] );
		$this->assertSame( array( 'Az összes bővítmény mutatása' ), $messages['Show all plugins'] );
		$this->assertSame( 'hu', $messages['']['lang'] );
		$this->assertArrayNotHasKey( 'This is not an LW plugin.', $messages );
	}

	public function test_po_keeps_the_plugin_entries_byte_for_byte(): void {
		$this->sync();

		$po = (string) file_get_contents( $this->dir . '/languages/lw-demo-hu_HU.po' );

		$this->assertStringStartsWith( self::PO, $po );
		$this->assertStringContainsString( "msgid \"This is not an LW plugin.\"\nmsgstr \"Ez nem LW bővítmény.\"", $po );
		$this->assertFileExists( $this->dir . '/languages/lw-demo-hu_HU.mo' );
	}

	public function test_a_second_run_changes_nothing(): void {
		$this->sync();

		$this->assertSame( array(), array_values( array_filter( $this->sync(), static fn( string $line ): bool => ! str_starts_with( $line, 'note' ) ) ) );
	}

	public function test_pot_is_left_to_the_plugin_i18n_flow_with_its_flags(): void {
		$log = $this->sync();

		$this->assertSame( self::POT, file_get_contents( $this->dir . '/languages/lw-demo.pot' ) );
		$this->assertStringContainsString( 'run `npm run i18n`', implode( "\n", $log ) );
	}

	public function test_pot_without_an_i18n_flow_only_gains_the_missing_hub_entries(): void {
		unlink( $this->dir . '/package.json' );
		$this->sync();

		$pot = (string) file_get_contents( $this->dir . '/languages/lw-demo.pot' );

		$this->assertStringStartsWith( self::POT, $pot );
		$this->assertStringContainsString( "#, php-format,js-format\nmsgid \"today %s\"", $pot );
		$this->assertStringContainsString( "#: assets/hub/index.js:1\nmsgid \"Try again\"\nmsgstr \"\"", $pot );
		$this->assertSame( array(), $this->sync() );
	}

	public function test_i18n_flow_detection(): void {
		$root = $this->dir . '/';

		$this->assertSame( 'npm run i18n', I18nFlow::command( $root ) );
		$this->assertFalse( I18nFlow::excludes_hub( $root ) );

		file_put_contents( $root . 'package.json', (string) json_encode( array( 'scripts' => array( 'pot' => 'wp i18n make-pot . x.pot --exclude=vendor,assets,src' ) ) ) );
		$this->assertSame( 'npm run pot', I18nFlow::command( $root ) );
		$this->assertTrue( I18nFlow::excludes_hub( $root ) );

		unlink( $root . 'package.json' );
		$this->assertNull( I18nFlow::command( $root ) );
	}

	/**
	 * Run the merge; returns the writer log.
	 *
	 * @return array<int, string>
	 */
	private function sync(): array {
		$target = new Target( $this->dir, array() );
		$writer = new Writer( $target->root, false );

		( new TranslationMerger( dirname( __DIR__, 2 ) . '/', $target, $writer ) )->merge();

		return $writer->log();
	}

	/**
	 * The hub script JSON.
	 *
	 * @return array<string, mixed>
	 */
	private function json(): array {
		$file = $this->dir . '/languages/lw-demo-hu_HU-' . md5( 'assets/hub/index.js' ) . '.json';
		$this->assertFileExists( $file );

		return (array) json_decode( (string) file_get_contents( $file ), true );
	}
}
