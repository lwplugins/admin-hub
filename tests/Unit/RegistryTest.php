<?php
/**
 * Registry loading and merge tests.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tests\Unit;

use Brain\Monkey\Functions;
use LightweightPlugins\AdminHub\Registry;
use LightweightPlugins\AdminHub\RegistryFallback;

/**
 * Registry::merge() and Registry::get().
 */
final class RegistryTest extends MonkeyTestCase {

	/**
	 * A small bundled list.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function fallback(): array {
		return array(
			'lw-seo'     => array(
				'name'          => 'LW SEO',
				'description'   => 'Bundled SEO text.',
				'icon_color'    => '#2271b1',
				'constant'      => 'LW_SEO_VERSION',
				'settings_page' => 'lw-seo',
				'github'        => 'https://github.com/lwplugins/lw-seo',
				'status'        => 'stable',
			),
			'lw-disable' => array(
				'name'          => 'LW Disable',
				'description'   => 'Bundled Disable text.',
				'icon_color'    => '#d63638',
				'constant'      => 'LW_DISABLE_VERSION',
				'settings_page' => 'lw-disable',
				'github'        => 'https://github.com/lwplugins/lw-disable',
				'status'        => 'stable',
			),
		);
	}

	public function test_remote_list_wins_and_takes_missing_fields_from_bundle(): void {
		$list = Registry::merge(
			array(
				'lw-seo' => array(
					'name'        => 'LW SEO Remote',
					'description' => '',
					'constant'    => 'LW_SEO_VERSION',
				),
				'lw-new' => array( 'name' => 'LW New' ),
			),
			$this->fallback()
		);

		$this->assertSame( array( 'lw-seo', 'lw-new' ), array_keys( $list ) );
		$this->assertSame( 'LW SEO Remote', $list['lw-seo']['name'] );
		$this->assertSame( 'Bundled SEO text.', $list['lw-seo']['description'] );
		$this->assertSame( 'lw-seo', $list['lw-seo']['settings_page'] );
		$this->assertSame( '', $list['lw-new']['constant'] );
		$this->assertArrayNotHasKey( 'lw-disable', $list, 'The remote list decides which plugins are listed.' );
	}

	public function test_hidden_and_malformed_entries_are_dropped(): void {
		$list = Registry::merge(
			array(
				'lw-seo'     => array( 'name' => 'LW SEO', 'status' => 'hidden' ),
				'../evil'    => array( 'name' => 'Evil' ),
				'UPPER'      => array( 'name' => 'Upper' ),
				0            => array( 'name' => 'Numeric' ),
				'lw-string'  => 'not-an-array',
				'lw-disable' => array(
					'name'  => array( 'nested' ),
					'color' => 'x',
				),
			),
			$this->fallback()
		);

		$this->assertSame( array( 'lw-disable' ), array_keys( $list ) );
		$this->assertSame( 'LW Disable', $list['lw-disable']['name'], 'A non-scalar field falls back to the bundle.' );
	}

	public function test_empty_or_useless_remote_falls_back_to_bundle(): void {
		$this->assertSame( array( 'lw-seo', 'lw-disable' ), array_keys( Registry::merge( array(), $this->fallback() ) ) );
		$this->assertSame( array( 'lw-seo', 'lw-disable' ), array_keys( Registry::merge( array( 'x' => 1 ), $this->fallback() ) ) );
	}

	public function test_name_defaults_to_slug(): void {
		$list = Registry::merge( array( 'lw-x' => array( 'description' => 'd' ) ), array() );

		$this->assertSame( 'lw-x', $list['lw-x']['name'] );
	}

	public function test_bundled_fallback_leaves_out_unlisted_plugins(): void {
		$slugs = array_keys( RegistryFallback::get() );

		$this->assertCount( 12, $slugs );
		$this->assertNotContains( 'lw-memberships', $slugs );
		$this->assertNotContains( 'lw-slider', $slugs );
		$this->assertContains( 'lw-scan', $slugs );
		foreach ( RegistryFallback::get() as $slug => $entry ) {
			$this->assertFileExists( dirname( __DIR__, 2 ) . '/icons/' . $slug . '.svg' );
			$this->assertMatchesRegularExpression( '/^LW_[A-Z_]+_VERSION$/', $entry['constant'] );
		}
	}

	public function test_get_uses_cached_json_without_fetching(): void {
		Functions\expect( 'get_transient' )->once()->with( Registry::CACHE_KEY )->andReturn(
			array( 'lw-seo' => array( 'name' => 'Cached SEO' ) )
		);
		Functions\expect( 'wp_remote_get' )->never();

		$list = Registry::get();

		$this->assertSame( array( 'lw-seo' ), array_keys( $list ) );
		$this->assertSame( 'Cached SEO', $list['lw-seo']['name'] );
	}

	public function test_get_fetches_and_caches_raw_json_on_a_cold_cache(): void {
		$raw = array( 'lw-scan' => array( 'name' => 'LW Scan' ) );

		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_remote_get' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( (string) json_encode( $raw ) );
		Functions\expect( 'set_transient' )->once()->with( Registry::CACHE_KEY, $raw, Registry::CACHE_TTL );

		$this->assertSame( array( 'lw-scan' ), array_keys( Registry::get() ) );
	}

	public function test_get_uses_bundle_when_remote_fails_and_does_not_cache_it(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_remote_get' )->justReturn( new \WP_Error( 'http', 'down' ) );
		Functions\expect( 'set_transient' )->never();

		$this->assertSame( array_keys( RegistryFallback::get() ), array_keys( Registry::get() ) );
	}
}
