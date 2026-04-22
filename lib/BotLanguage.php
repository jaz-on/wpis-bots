<?php
/**
 * API language tags for deduplication and Polylang mapping.
 *
 * @package WPIS\Bots
 */

namespace WPIS\Bots;

/**
 * Normalizes BCP-47 / ISO codes from social APIs and maps them to Polylang slugs when possible.
 */
final class BotLanguage {

	/**
	 * Short language key for deduplication (WordPress `sanitize_key`–friendly).
	 *
	 * @param string $raw From Mastodon `language` or the first Bluesky `langs` entry.
	 */
	public static function normalize_dedup_lang( string $raw ): string {
		$t = trim( $raw );
		if ( '' === $t ) {
			return 'en';
		}
		$t = strtolower( str_replace( '_', '-', $t ) );
		$base = \strstr( $t, '-', true );
		if ( false === $base ) {
			$base = $t;
		}
		$out = $base;
		if ( \strlen( $out ) < 2 ) {
			$out = 'en';
		}
		return \sanitize_key( $out );
	}

	/**
	 * Returns a valid Polylang slug for the given API tag, or empty if none matches.
	 */
	public static function map_api_to_polylang_slug( string $api_raw ): string {
		if ( ! \function_exists( 'pll_languages_list' ) ) {
			return '';
		}
		$valid = \pll_languages_list( array( 'fields' => 'slug' ) );
		if ( ! \is_array( $valid ) || array() === $valid ) {
			return '';
		}
		$t = \strtolower( \str_replace( '_', '-', \trim( $api_raw ) ) );
		if ( '' === $t ) {
			return '';
		}
		if ( \in_array( $t, $valid, true ) ) {
			return $t;
		}
		$base = \strstr( $t, '-', true );
		if ( false === $base ) {
			$base = $t;
		}
		if ( \strlen( $base ) >= 2 && \in_array( $base, $valid, true ) ) {
			return $base;
		}
		foreach ( $valid as $slug ) {
			if ( $t === $slug || ( \is_string( $slug ) && \str_starts_with( $t, $slug . '-' ) ) ) {
				return $slug;
			}
		}
		return '';
	}

	/**
	 * Prefer a mapped API tag; otherwise use a saved settings slug if it is still valid in Polylang.
	 */
	public static function resolve_polylang_slug( string $api_raw, string $settings_slug ): string {
		$mapped = self::map_api_to_polylang_slug( $api_raw );
		if ( '' !== $mapped ) {
			return $mapped;
		}
		$fb = \sanitize_key( (string) $settings_slug );
		if ( '' === $fb || ! \function_exists( 'pll_languages_list' ) ) {
			return '';
		}
		$valid = \pll_languages_list( array( 'fields' => 'slug' ) );
		if ( \is_array( $valid ) && \in_array( $fb, $valid, true ) ) {
			return $fb;
		}
		return '';
	}
}
