<?php
/**
 * bin/sync.php PO merge tests.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tests\Unit;

use LightweightPlugins\AdminHub\Tools\Sync\HubScriptPo;
use LightweightPlugins\AdminHub\Tools\Sync\PoMerger;
use PHPUnit\Framework\TestCase;

/**
 * Plugin-first, format-preserving merge and the hub script PO.
 */
final class SyncPoMergerTest extends TestCase {

	private const HOST = <<<'PO'
# Copyright (C) 2026 LW Plugins
msgid ""
msgstr ""
"Language: hu\n"
"X-Domain: lw-scan\n"

#. translators: %s: time of day.
#: includes/Admin/Format.php:35
#: build/index.js:1
#, php-format,js-format
msgid "today %s"
msgstr "ma %s"

#: assets/hub/index.js:1 build/index.js:1
msgid "Try again"
msgstr "Próbáld újra"

#: assets/hub/index.js:4
msgid "Loading…"
msgstr ""

#~ msgid "Old"
#~ msgstr "Régi"

#~ msgid "Gone"
#~ msgstr "Eltűnt"

PO;

	private const HUB = <<<'PO'
msgid ""
msgstr ""
"Language: hu_HU\n"
"X-Domain: lw-scan\n"

#: assets/hub/index.js:1
msgid "Try again"
msgstr "Újrapróbálás"

#: assets/hub/index.js:4
msgid "Loading…"
msgstr "Betöltés…"

#: assets/hub/index.js:5
msgid "Show all plugins"
msgstr "Az összes bővítmény mutatása"

#: assets/hub/index.js:5
#, fuzzy
msgid "Old"
msgstr "Régi"

#: includes/Admin/Hub/Page.php:57
msgid "LW Plugins"
msgstr "LW Plugins"

PO;

	public function test_the_plugin_translation_wins(): void {
		$out = PoMerger::merge( self::HOST, self::HUB );

		$this->assertStringContainsString( "msgid \"Try again\"\nmsgstr \"Próbáld újra\"", $out );
		$this->assertStringNotContainsString( 'Újrapróbálás', $out );
	}

	public function test_hub_only_entries_are_added_before_the_obsolete_ones(): void {
		$out = PoMerger::merge( self::HOST, self::HUB );

		$this->assertStringContainsString( "#: assets/hub/index.js:5\nmsgid \"Show all plugins\"\nmsgstr \"Az összes bővítmény mutatása\"\n\n", $out );
		$this->assertLessThan( strpos( $out, '#~ msgid "Gone"' ), strpos( $out, 'Show all plugins' ) );
	}

	public function test_an_obsolete_plugin_entry_is_revived_in_place(): void {
		$out = PoMerger::merge( self::HOST, self::HUB );

		$this->assertStringContainsString( "#: assets/hub/index.js:5\nmsgid \"Old\"\nmsgstr \"Régi\"\n", $out );
		$this->assertStringNotContainsString( '#~ msgid "Old"', $out );
		$this->assertStringContainsString( "#~ msgid \"Gone\"\n#~ msgstr \"Eltűnt\"\n", $out );
		$this->assertSame( 1, substr_count( $out, 'msgid "Old"' ) );
	}

	public function test_an_empty_plugin_msgstr_takes_the_hub_translation(): void {
		$out = PoMerger::merge( self::HOST, self::HUB );

		$this->assertStringContainsString( "#: assets/hub/index.js:4\nmsgid \"Loading…\"\nmsgstr \"Betöltés…\"", $out );
	}

	public function test_the_host_formatting_and_flags_are_kept(): void {
		$out = PoMerger::merge( self::HOST, self::HUB );

		$this->assertStringStartsWith( substr( self::HOST, 0, (int) strpos( self::HOST, '#: assets/hub/index.js:4' ) ), $out );
		$this->assertStringContainsString( "#: includes/Admin/Format.php:35\n#: build/index.js:1\n#, php-format,js-format\n", $out );
	}

	public function test_a_second_merge_is_a_no_op(): void {
		$once = PoMerger::merge( self::HOST, self::HUB );

		$this->assertSame( $once, PoMerger::merge( $once, self::HUB ) );
		$this->assertSame( array(), PoMerger::missing( $once, self::HUB ) );
	}

	public function test_missing_lists_the_hub_keys_the_host_lacks(): void {
		$this->assertSame( array( 'Show all plugins', 'Old', 'LW Plugins' ), PoMerger::missing( self::HOST, self::HUB ) );
	}

	public function test_the_script_po_has_the_host_wording_of_the_hub_script_strings_only(): void {
		$merged = PoMerger::merge( self::HOST, self::HUB );
		$po     = HubScriptPo::build( $merged, self::HUB, 'assets/hub/index.js' );

		$this->assertStringStartsWith( "# Copyright (C) 2026 LW Plugins\nmsgid \"\"\nmsgstr \"\"\n\"Language: hu\\n\"", $po );
		$this->assertStringContainsString( "#: assets/hub/index.js\nmsgid \"Try again\"\nmsgstr \"Próbáld újra\"", $po );
		$this->assertStringContainsString( "#: assets/hub/index.js\nmsgid \"Show all plugins\"", $po );
		$this->assertStringNotContainsString( 'build/index.js', $po );
		$this->assertStringNotContainsString( 'today %s', $po );
		$this->assertStringNotContainsString( 'LW Plugins"', $po );
		$this->assertStringNotContainsString( '#~', $po );
	}
}
