<?php
/**
 * Pure helpers for bot text handling (testable without WordPress).
 *
 * @package WPIS\Bots
 */

namespace WPIS\Bots;

/**
 * Text truncation and keyword matching.
 */
final class TextHelper {

	/**
	 * Clamp UTF-8 text length for quote body.
	 *
	 * @param string $text Raw text.
	 * @param int    $max  Max characters (grapheme-aware when mbstring available).
	 * @return string
	 */
	public static function truncate_body( string $text, int $max = 1000 ): string {
		$text = trim( $text );
		if ( $max < 1 ) {
			return '';
		}
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $text, 'UTF-8' ) <= $max ) {
				return $text;
			}
			return mb_substr( $text, 0, $max, 'UTF-8' );
		}
		if ( strlen( $text ) <= $max ) {
			return $text;
		}
		return substr( $text, 0, $max );
	}

	/**
	 * Whether text matches any line pattern (substring match, case-insensitive). Empty lines ignored.
	 *
	 * @param string   $text     Haystack.
	 * @param string[] $patterns Lines from settings.
	 * @return bool
	 */
	public static function matches_any_pattern( string $text, array $patterns ): bool {
		if ( array() === $patterns ) {
			return true;
		}
		$lower = strtolower( $text );
		foreach ( $patterns as $p ) {
			$p = trim( (string) $p );
			if ( '' === $p ) {
				continue;
			}
			if ( str_contains( $lower, strtolower( $p ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Parse textarea lines into non-empty strings.
	 *
	 * @param string $textarea Multiline string.
	 * @return string[]
	 */
	public static function patterns_from_textarea( string $textarea ): array {
		$lines = preg_split( '/\R/u', $textarea );
		if ( false === $lines ) {
			$lines = array();
		}
		$out = array();
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}
}
