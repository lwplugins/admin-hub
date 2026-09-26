<?php
/**
 * Installed / active detection tests.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tests\Unit;

use Brain\Monkey\Functions;
use LightweightPlugins\AdminHub\Detector;

/**
 * Detector.
 */
final class DetectorTest extends MonkeyTestCase {

	/**
	 * Detector over a fake plugin list, with "lw-seo" active.
	 *
	 * @return Detector
	 */
	private function detector(): Detector {
		Functions\when( 'is_plugin_active' )->alias( static fn( string $file ): bool => 'lw-seo/lw-seo.php' === $file );

		return new Detector(
			array(
				'lw-seo/lw-seo.php'           => array( 'Version' => '1.7.3' ),
				'lw-enable/lw-enable.php'     => array( 'Version' => '1.1.0' ),
				'lw-img-main/lw-img.php'      => array( 'Version' => '2.0.0' ),
				'lw-cookie/cookie-loader.php' => array( 'Version' => '1.8.0' ),
				'hello.php'                   => array( 'Version' => '1.7' ),
			)
		);
	}

	public function test_find_file_matches_by_directory_only(): void {
		$detector = $this->detector();

		$this->assertSame( 'lw-seo/lw-seo.php', $detector->find_file( 'lw-seo' ) );
		$this->assertSame( 'lw-cookie/cookie-loader.php', $detector->find_file( 'lw-cookie' ) );
		$this->assertNull( $detector->find_file( 'lw-img' ), 'A differently named directory is not a match.' );
		$this->assertNull( $detector->find_file( 'hello' ) );
		$this->assertNull( $detector->find_file( '.' ) );
		$this->assertNull( $detector->find_file( '../lw-seo' ) );
	}

	public function test_states(): void {
		$detector = $this->detector();

		$this->assertSame(
			array( 'status' => 'active', 'version' => '1.7.3', 'file' => 'lw-seo/lw-seo.php' ),
			$detector->detect( 'lw-seo', array( 'constant' => 'LW_HUB_TEST_UNDEFINED' ) )
		);
		$this->assertSame(
			array( 'status' => 'inactive', 'version' => '1.1.0', 'file' => 'lw-enable/lw-enable.php' ),
			$detector->detect( 'lw-enable', array( 'constant' => 'LW_ENABLE_VERSION_UNDEFINED' ) )
		);
		$this->assertSame(
			array( 'status' => 'missing', 'version' => null, 'file' => null ),
			$detector->detect( 'lw-lms', array( 'constant' => 'LW_LMS_VERSION_UNDEFINED' ) )
		);
	}

	public function test_defined_constant_counts_as_active_without_a_plugin_file(): void {
		define( 'LW_HUB_TEST_COMPOSER_VERSION', '3.1.4' );

		$state = $this->detector()->detect( 'lw-composer', array( 'constant' => 'LW_HUB_TEST_COMPOSER_VERSION' ) );

		$this->assertSame( array( 'status' => 'active', 'version' => '3.1.4', 'file' => null ), $state );
	}

	public function test_invalid_constant_names_are_not_evaluated(): void {
		$state = $this->detector()->detect( 'lw-lms', array( 'constant' => 'PHP_VERSION; exit' ) );

		$this->assertSame( 'missing', $state['status'] );
		$this->assertSame( 'missing', $this->detector()->detect( 'lw-lms', array() )['status'] );
	}
}
