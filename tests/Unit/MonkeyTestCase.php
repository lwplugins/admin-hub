<?php
/**
 * Base test case wiring Brain Monkey setup/teardown.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Brain Monkey lifecycle plus the translation and error helpers every test
 * needs.
 */
abstract class MonkeyTestCase extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
		Functions\when( 'is_wp_error' )->alias( static fn( $thing ): bool => $thing instanceof \WP_Error );
	}

	/**
	 * Tear down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
