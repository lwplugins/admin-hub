<?php
/**
 * Version negotiation tests.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tests\Unit;

use LightweightPlugins\AdminHub\Negotiator;
use PHPUnit\Framework\TestCase;

/**
 * Negotiator::winner().
 */
final class NegotiatorTest extends TestCase {

	public function test_highest_version_wins(): void {
		$winner = Negotiator::winner(
			array(
				array( 'version' => '1.0.0', 'class' => 'A\\Hub' ),
				array( 'version' => '1.2.0', 'class' => 'B\\Hub' ),
				array( 'version' => '1.1.9', 'class' => 'C\\Hub' ),
			)
		);

		$this->assertSame( 'B\\Hub', $winner['class'] );
	}

	public function test_versions_compare_numerically_not_as_strings(): void {
		$winner = Negotiator::winner(
			array(
				array( 'version' => '1.9.0', 'class' => 'A\\Hub' ),
				array( 'version' => '1.10.0', 'class' => 'B\\Hub' ),
			)
		);

		$this->assertSame( 'B\\Hub', $winner['class'] );
	}

	public function test_tie_goes_to_lowest_class_name_whatever_the_order(): void {
		$a = array( 'version' => '1.0.0', 'class' => 'LightweightPlugins\\Disable\\Admin\\Hub\\Hub' );
		$b = array( 'version' => '1.0.0', 'class' => 'LightweightPlugins\\Cookie\\Admin\\Hub\\Hub' );

		$this->assertSame( $b['class'], Negotiator::winner( array( $a, $b ) )['class'] );
		$this->assertSame( $b['class'], Negotiator::winner( array( $b, $a ) )['class'] );
	}

	public function test_malformed_candidates_are_ignored(): void {
		$winner = Negotiator::winner(
			array(
				'junk',
				array( 'version' => '9.9.9' ),
				array( 'version' => 'dev-main', 'class' => 'X\\Hub' ),
				array( 'version' => array(), 'class' => 'Y\\Hub' ),
				array( 'version' => '1.0.0', 'class' => 'Ok\\Hub', 'file' => '/p/ok.php' ),
			)
		);

		$this->assertSame(
			array( 'version' => '1.0.0', 'class' => 'Ok\\Hub', 'file' => '/p/ok.php' ),
			$winner
		);
	}

	public function test_no_candidates_means_no_winner(): void {
		$this->assertNull( Negotiator::winner( array() ) );
		$this->assertNull( Negotiator::winner( array( null, 'x' ) ) );
	}
}
