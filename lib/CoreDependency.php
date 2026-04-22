<?php
/**
 * WPIS Core (wpis-core) availability for admin screens.
 *
 * @package WPIS\Bots
 */

namespace WPIS\Bots;

/**
 * Prints a notice when quote API is missing; returns whether the page should stop rendering the form.
 */
final class CoreDependency {

	/**
	 * Whether WPIS Core exposes everything bots need for ingestion (submit + duplicate check).
	 *
	 * @return bool
	 */
	public static function is_core_ready(): bool {
		return function_exists( 'wpis_submit_quote_candidate' )
			&& function_exists( 'wpis_find_potential_duplicates' );
	}

	/**
	 * @return bool True if core is missing (caller may return early).
	 */
	public static function block_if_core_missing(): bool {
		if ( self::is_core_ready() ) {
			return false;
		}
		echo '<div class="notice notice-error"><p>';
		if ( ! function_exists( 'wpis_submit_quote_candidate' ) && ! function_exists( 'wpis_find_potential_duplicates' ) ) {
			esc_html_e(
				'WPIS Bots needs WordPress Is… Core: install and activate the wpis-core package (folder wpis-core, file wpis-core.php).',
				'wpis-bots'
			);
		} elseif ( ! function_exists( 'wpis_submit_quote_candidate' ) ) {
			esc_html_e(
				'WPIS Core is incomplete: wpis_submit_quote_candidate() is missing. Update the wpis-core plugin.',
				'wpis-bots'
			);
		} else {
			esc_html_e(
				'WPIS Core is incomplete: wpis_find_potential_duplicates() is missing. Update the wpis-core plugin.',
				'wpis-bots'
			);
		}
		echo '</p></div>';
		return true;
	}
}
