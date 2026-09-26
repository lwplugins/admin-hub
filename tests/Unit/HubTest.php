<?php
/**
 * Hub bootstrap tests.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use LightweightPlugins\AdminHub\Hub;

/**
 * Hub::init(), candidates and the winner gate.
 */
final class HubTest extends MonkeyTestCase {

	protected function setUp(): void {
		parent::setUp();
		Hub::reset();
	}

	protected function tearDown(): void {
		Hub::reset();
		parent::tearDown();
	}

	public function test_init_hooks_in_before_legacy_menus_and_only_once(): void {
		Hub::init( '/plugins/lw-disable/lw-disable.php' );
		Hub::init( '/plugins/other/other.php' );

		$this->assertSame( 5, has_action( 'admin_menu', array( Hub::class, 'on_admin_menu' ) ) );
		$this->assertNotFalse( has_action( 'rest_api_init', array( Hub::class, 'on_rest_api_init' ) ) );
		$this->assertNotFalse( has_filter( Hub::CANDIDATES_FILTER, array( Hub::class, 'add_candidate' ) ) );
		$this->assertSame( '/plugins/lw-disable/lw-disable.php', Hub::plugin_file() );
	}

	public function test_add_candidate_appends_this_copy(): void {
		Hub::init( '/plugins/lw-disable/lw-disable.php' );

		$list = Hub::add_candidate( array( array( 'version' => '0.9.0', 'class' => 'Old\\Hub' ) ) );

		$this->assertCount( 2, $list );
		$this->assertSame(
			array(
				'version' => Hub::VERSION,
				'class'   => Hub::class,
				'file'    => '/plugins/lw-disable/lw-disable.php',
			),
			$list[1]
		);
		$this->assertCount( 1, Hub::add_candidate( 'not-an-array' ) );
	}

	public function test_newer_copy_elsewhere_makes_this_one_idle(): void {
		Filters\expectApplied( Hub::CANDIDATES_FILTER )
			->once()
			->andReturn(
				array(
					array( 'version' => Hub::VERSION, 'class' => Hub::class ),
					array( 'version' => '99.0.0', 'class' => 'Newer\\Hub' ),
				)
			);
		Functions\expect( 'add_menu_page' )->never();
		Functions\expect( 'register_rest_route' )->never();

		Hub::on_admin_menu();
		Hub::on_rest_api_init();

		$this->assertFalse( Hub::is_winner() );
	}

	public function test_winning_copy_registers_the_page(): void {
		Filters\expectApplied( Hub::CANDIDATES_FILTER )
			->once()
			->andReturn(
				array(
					array( 'version' => '0.1.0', 'class' => 'Legacy\\Hub' ),
					array( 'version' => Hub::VERSION, 'class' => Hub::class ),
				)
			);
		Functions\expect( 'add_menu_page' )
			->once()
			->with( 'LW Plugins', 'LW Plugins', 'manage_options', 'lw-plugins', \Mockery::type( 'array' ), 'dashicons-superhero-alt', 80 )
			->andReturn( 'toplevel_page_lw-plugins' );
		Actions\expectAdded( 'admin_enqueue_scripts' )->once();

		Hub::on_admin_menu();

		$this->assertTrue( Hub::is_winner() );
	}
}
