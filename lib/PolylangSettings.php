<?php
/**
 * Polylang availability for bot options UI and sanitization.
 *
 * @package WPIS\Bots
 */

namespace WPIS\Bots;

/**
 * Polylang helper for the bots package.
 */
final class PolylangSettings {

	/**
	 * True when Polylang functions are available (plugin active).
	 */
	public static function is_active(): bool {
		return \function_exists( 'pll_languages_list' );
	}
}
