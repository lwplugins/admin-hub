<?php
/**
 * Activation guard tests.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tests\Unit;

use Brain\Monkey\Functions;
use LightweightPlugins\AdminHub\Activator;
use LightweightPlugins\AdminHub\Catalog;
use LightweightPlugins\AdminHub\Detector;

/**
 * Activator::activate().
 */
final class ActivatorTest extends MonkeyTestCase {

	/**
	 * Activator where lw-enable is installed and inactive, lw-seo active,
	 * lw-lms listed but not installed, and "rogue" installed but unlisted.
	 *
	 * @return Activator
	 */
	private function activator(): Activator {
		Functions\when( 'is_plugin_active' )->alias( static fn( string $file ): bool => 'lw-seo/lw-seo.php' === $file );

		$registry = array(
			'lw-enable' => array( 'constant' => 'LW_HUB_TEST_ENABLE' ),
			'lw-seo'    => array( 'constant' => 'LW_HUB_TEST_SEO' ),
			'lw-lms'    => array( 'constant' => 'LW_HUB_TEST_LMS' ),
		);
		$detector = new Detector(
			array(
				'lw-enable/lw-enable.php' => array( 'Version' => '1.1.0' ),
				'lw-seo/lw-seo.php'       => array( 'Version' => '1.7.3' ),
				'rogue/rogue.php'         => array( 'Version' => '6.6.6' ),
			)
		);

		return new Activator( new Catalog( $registry, $detector ) );
	}

	public function test_activates_the_matched_plugin_file(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'activate_plugin' )->once()->with( 'lw-enable/lw-enable.php' )->andReturn( null );

		$this->assertNull( $this->activator()->activate( 'lw-enable' ) );
	}

	public function test_unlisted_plugins_are_refused_even_when_installed(): void {
		Functions\expect( 'activate_plugin' )->never();

		foreach ( array( 'rogue', '../rogue', 'rogue/rogue.php', '' ) as $slug ) {
			$error = $this->activator()->activate( $slug );
			$this->assertSame( 'lw_hub_unknown_plugin', $error->get_error_code() );
			$this->assertSame( array( 'status' => 404 ), $error->get_error_data() );
		}
	}

	public function test_listed_but_not_installed_is_refused(): void {
		Functions\expect( 'activate_plugin' )->never();

		$error = $this->activator()->activate( 'lw-lms' );

		$this->assertSame( 'lw_hub_not_installed', $error->get_error_code() );
		$this->assertSame( array( 'status' => 409 ), $error->get_error_data() );
	}

	public function test_already_active_is_a_no_op(): void {
		Functions\expect( 'activate_plugin' )->never();

		$this->assertNull( $this->activator()->activate( 'lw-seo' ) );
	}

	public function test_per_plugin_capability_is_checked(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'activate_plugin', 'lw-enable/lw-enable.php' )->andReturn( false );
		Functions\expect( 'activate_plugin' )->never();

		$this->assertSame( 'lw_hub_forbidden', $this->activator()->activate( 'lw-enable' )->get_error_code() );
	}

	public function test_activation_errors_are_passed_on(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'activate_plugin' )->justReturn( new \WP_Error( 'plugin_invalid', 'Broken plugin.' ) );

		$error = $this->activator()->activate( 'lw-enable' );

		$this->assertSame( 'lw_hub_activation_failed', $error->get_error_code() );
		$this->assertSame( 'Broken plugin.', $error->get_error_message() );
		$this->assertSame( array( 'status' => 500 ), $error->get_error_data() );
	}
}
