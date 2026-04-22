<?php
/**
 * WPIS Core (wpis-plugin) availability for admin screens.
 *
 * @package WPIS\Bots
 */

namespace WPIS\Bots;

/**
 * Prints a notice when quote API is missing; returns whether the page should stop rendering the form.
 */
final class CoreDependency {

	/**
	 * @return bool True if core is missing (caller may return early).
	 */
	public static function block_if_core_missing(): bool {
		if ( function_exists( 'wpis_submit_quote_candidate' ) ) {
			return false;
		}
		echo '<div class="notice notice-error"><p>';
		esc_html_e(
			'WPIS Bots needs WordPress Is… Core: install and activate the wpis-plugin package (folder wpis-plugin, file wpis-plugin.php).',
			'wpis-bots'
		);
		echo '</p></div>';
		return true;
	}
}
