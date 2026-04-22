<?php
/**
 * Tests for WPIS\Bots\TextHelper.
 *
 * @package WPIS\Bots\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPIS\Bots\TextHelper;

final class TextHelperTest extends TestCase {

	public function test_truncate_body_respects_max(): void {
		$s = str_repeat( 'é', 10 );
		if ( function_exists( 'mb_substr' ) ) {
			$this->assertSame( 5, mb_strlen( TextHelper::truncate_body( $s, 5 ), 'UTF-8' ) );
		} else {
			$this->assertLessThanOrEqual( 10, strlen( TextHelper::truncate_body( $s, 5 ) ) );
		}
	}

	public function test_matches_any_pattern_empty_always_true(): void {
		$this->assertTrue( TextHelper::matches_any_pattern( 'WordPress is great', array() ) );
	}

	public function test_matches_any_pattern_substring(): void {
		$this->assertTrue( TextHelper::matches_any_pattern( 'Say WordPress is good', array( 'wordpress is' ) ) );
		$this->assertFalse( TextHelper::matches_any_pattern( 'Drupal only', array( 'wordpress is' ) ) );
	}

	public function test_patterns_from_textarea(): void {
		$p = TextHelper::patterns_from_textarea( "a\n\nb\n" );
		$this->assertSame( array( 'a', 'b' ), $p );
	}
}
